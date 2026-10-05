<?php

namespace MauticPlugin\JotaworksEmailTrackingBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Form\Type\SlotButtonType;
use Mautic\CoreBundle\Form\Type\SlotCodeModeType;
use Mautic\CoreBundle\Form\Type\SlotDynamicContentType;
use Mautic\CoreBundle\Form\Type\SlotImageCaptionType;
use Mautic\CoreBundle\Form\Type\SlotImageCardType;
use Mautic\CoreBundle\Form\Type\SlotSeparatorType;
use Mautic\CoreBundle\Form\Type\SlotSocialFollowType;
use Mautic\CoreBundle\Form\Type\SlotTextType;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\EmojiHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\PageBundle\Entity\Redirect;
use Mautic\PageBundle\Entity\Trackable;
use Mautic\PageBundle\Model\RedirectModel;
use Mautic\PageBundle\Model\TrackableModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use MauticPlugin\JotaworksEmailTrackingBundle\Integration\EmailTrackingSettings;
use Mautic\PageBundle\PageEvents;
use Mautic\LeadBundle\Entity\Lead;

class BuilderSubscriber implements EventSubscriberInterface
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var EmailModel
     */
    private $emailModel;

    /**
     * @var TrackableModel
     */
    private $pageTrackableModel;

    /**
     * @var RedirectModel
     */
    private $pageRedirectModel;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var EmailTrackingSettings
     */
    private $trackingSettings;

    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        EmailModel $emailModel,
        TrackableModel $trackableModel,
        RedirectModel $redirectModel,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        EmailTrackingSettings $trackingSettings
    ) {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->emailModel           = $emailModel;
        $this->pageTrackableModel   = $trackableModel;
        $this->pageRedirectModel    = $redirectModel;
        $this->translator           = $translator;
        $this->entityManager        = $entityManager;
        $this->trackingSettings = $trackingSettings;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_SEND  => [
                // Ensure this is done last in order to catch all tokenized URLs
                ['convertUrlsToTokens', -100000],
            ],
            EmailEvents::EMAIL_ON_DISPLAY => [
                // Ensure this is done last in order to catch all tokenized URLs
                ['convertUrlsToTokens', -100000],
            ],
        ];
    }

    protected function isInDoNotTrackUrl($url, $donottrackUrls)
    {
        // Ensure it's not in the do not track list
        foreach ($donottrackUrls as $notTrackable) {
            // Skip empty patterns: an empty regex (~~) would match every URL and
            // disable tracking for all links (e.g. when the config field is left blank).
            if ('' === $notTrackable) {
                continue;
            }
            if (preg_match('~'.$notTrackable.'~', $url)) {
                return true;
            }
        }

        return false;
    }

    protected function isInDoNotTrackEmail($emailId, $donottrackEmailsWithId)
    {
        if( !is_array($donottrackEmailsWithId) )
        {
            return false;
        }

        return in_array((string) $emailId, $donottrackEmailsWithId, true);
    }

    protected function isInDoNotTrackLead( $lead, $leadfieldNameDoNotTrack)
    {
        // Default is to TRACK. Only skip tracking when the configured lead field is
        // explicitly set to a falsy opt-out value (0/no/false). A missing or empty
        // field means "no decision" and must not disable tracking for every contact.
        if (empty($leadfieldNameDoNotTrack) || !array_key_exists($leadfieldNameDoNotTrack, $lead)) {
            return false;
        }

        $value = $lead[$leadfieldNameDoNotTrack];
        // A contact that was just created or updated in this request (e.g. by a form submit)
        // carries the normalized PHP value: boolean fields arrive as false, not as '0'.
        // (string) false === '' would otherwise be read as "no decision" and keep tracking.
        if (false === $value || 0 === $value) {
            return true;
        }
        if (null === $value || '' === $value) {
            return false;
        }

        return in_array((string) $value, ['0', 'no', 'false'], true);
    }

    protected function getUrlFromRedirectToken($token) 
    {
        //get hash from key 
        $re = '/{trackable=(.*)}/m';
        preg_match($re, $token, $matches);

        if( !$matches ||!isset($matches[1]) )
        {
            return false;
        }

        $redirectId = $matches[1];

        //get url from redirect 
        /** @var \Mautic\PageBundle\Model\RedirectModel $redirectModel */
        $redirect      = $this->pageRedirectModel->getRedirectById($redirectId);
        if(!$redirect)
        {
            return false;
        }

        return $redirect->getUrl();
    }

    protected function checkIfTrackableToken($token)
    {
        return strpos($token, 'trackable=') !== false;
    }

    /**
     * @return array
     */
    public function convertUrlsToTokens(EmailSendEvent $event)
    {

        if ($event->isInternalSend() || $this->coreParametersHelper->get('disable_trackable_urls')) {
            // Don't convert urls
            return;
        }

        if( !$this->trackingSettings->isEnabled() )
        {
            return;
        }
    
        $email   = $event->getEmail();
        $emailId = ($email) ? $email->getId() : null;
        $lead = $event->getLead();

        //prepare config settings
        $trackingSettings = $this->trackingSettings->getSettings();
        // Trim entries and drop empty ones so a blank config value does not become a
        // match-all rule (empty email id / empty url regex).
        $donottrackEmailsWithId = array_filter(array_map('trim', explode(',', (string) ($trackingSettings['donottrackEmailsWithId'] ?? ''))), 'strlen');
        $donottrackUrls = array_filter(array_map('trim', explode(',', (string) ($trackingSettings['donottrackUrls'] ?? ''))), 'strlen');
        $leadfieldNameDoNotTrack = $trackingSettings['leadfieldNameDoNotTrack'] ?? '';

        //get all tokens 
        $tokens = $event->getTokens();
        foreach($tokens as $key => $value)
        {
            $doNotTrack = false;
            if( $this->checkIfTrackableToken($key) )
            {
                $url = $this->getUrlFromRedirectToken($key);
                if(!$url)
                {
                    continue;
                }

                //restore token to original url if any of the following criteria is true
                if( $this->isInDoNotTrackEmail($emailId, $donottrackEmailsWithId) )
                {
                    $doNotTrack = true;   
                }

                if( $this->isInDoNotTrackUrl($url, $donottrackUrls) )
                {
                    $doNotTrack = true; 
                }

                if( $this->isInDoNotTrackLead( $lead, $leadfieldNameDoNotTrack) )
                {
                    $doNotTrack = true; 
                }
                if($doNotTrack)
                {
                    //reset settings to original
                    $event->addToken('{tracking_pixel}', "");                    
                    $event->addToken($key, $url);

                    continue;
                }

            }

        }

    }


}