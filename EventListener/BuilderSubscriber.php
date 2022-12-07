<?php

namespace MauticPlugin\JotaworksEmailTrackingBundle\EventListener;

use Doctrine\ORM\EntityManager;
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
     * @var EntityManager
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
        EntityManager $entityManager,
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
    public static function getSubscribedEvents()
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

        return in_array($emailId, $donottrackEmailsWithId);
    }

    protected function isInDoNotTrackLead( $lead, $leadfieldNameDoNotTrack)
    {

        if( !isset( $lead[$leadfieldNameDoNotTrack] ) )
        {
            return false;
        }

        //in test sendings this contains a string: [Einwilligung zum E-Mail Tracking]
        $value =  $lead[$leadfieldNameDoNotTrack];
        if($value) 
        {
            return true;
        }

        return false;
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
        $donottrackEmailsWithId = explode(',', $trackingSettings['donottrackEmailsWithId'] );
        $donottrackUrls = explode(',', $trackingSettings['donottrackUrls'] );
        $leadfieldNameDoNotTrack = $trackingSettings['leadfieldNameDoNotTrack'];

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