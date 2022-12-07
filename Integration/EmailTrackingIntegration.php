<?php

/*
 * @copyright   2020 MTCExtendee. All rights reserved
 * @author      MTCExtendee
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\JotaworksEmailTrackingBundle\Integration;

use Mautic\LeadBundle\Form\Type\LeadFieldsType;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class EmailTrackingIntegration extends AbstractIntegration
{
    const INTEGRATION_NAME = 'EmailTracking';

    public function getName()
    {
        return self::INTEGRATION_NAME;
    }

    public function getDisplayName()
    {
        return 'Email Tracking';
    }

    public function getAuthenticationType()
    {
        return 'none';
    }

    public function getIcon()
    {
        return 'plugins/JotaworksEmailTrackingBundle/Assets/img/icon.png';
    }

/**
     * @param \Mautic\PluginBundle\Integration\Form|FormBuilder $builder
     * @param array                                             $data
     * @param string                                            $formArea
     */
    public function appendToForm(&$builder, $data, $formArea)
    {
        if ('features' == $formArea) {

            $builder->add(
                'leadfieldNameDoNotTrack',
                TextType::class,
                [
                    'label'      => 'jw.emailtracking.leadfieldNameDoNotTrack',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => false,
                    'attr'       => [
                        'class'   => 'form-control',
                        'tooltip' => '',
                    ],
                ]
            );

            $builder->add(
                'donottrackEmailsWithId',
                TextType::class,
                [
                    'label'      => 'jw.emailtracking.donottrackEmailsWithIdr',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => false,
                    'attr'       => [
                        'class'   => 'form-control',
                        'tooltip' => '',
                    ],
                ]
            );

            $builder->add(
                'donottrackUrls',
                TextType::class,
                [
                    'label'      => 'jw.emailtracking.donottrackUrls',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => false,
                    'attr'       => [
                        'class'   => 'form-control',
                        'tooltip' => '',
                    ],
                ]
            );            

        }
    }
}
