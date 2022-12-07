<?php

/*
 * @copyright   2020 MTCExtendee. All rights reserved
 * @author      MTCExtendee
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\JotaworksEmailTrackingBundle\Integration;

use Mautic\CoreBundle\Helper\ArrayHelper;
use Mautic\PluginBundle\Helper\IntegrationHelper;

class EmailTrackingSettings
{
    /**
     * @var bool|\Mautic\PluginBundle\Integration\AbstractIntegration
     */
    private $integration;

    private $enabled = false;

    /**
     * @var array
     */
    private $settings = [];

    /**
     * DolistSettings constructor.
     *
     * @param IntegrationHelper $integrationHelper
     */
    public function __construct(IntegrationHelper $integrationHelper)
    {

        $this->integration = $integrationHelper->getIntegrationObject(EmailTrackingIntegration::INTEGRATION_NAME);
        if ($this->integration instanceof EmailTrackingIntegration && $this->integration->getIntegrationSettings()->getIsPublished() ) 
        {
        
           $settings           = $this->integration->getIntegrationSettings();
           $this->settings           = $settings->getFeatureSettings();

    

            $this->enabled  = true;
        }
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    public function getSettings() {

        if ($this->integration instanceof EmailTrackingIntegration && $this->integration->getIntegrationSettings()->getIsPublished() ) 
        {
           $settings           = $this->integration->getIntegrationSettings();
           return $settings->getFeatureSettings();
        }

        return [
            'leadfieldNameDoNotTrack' => '',
            'donottrackEmailsWithId' => '',
            'donottrackUrls' => ''
        ];
    }

}
