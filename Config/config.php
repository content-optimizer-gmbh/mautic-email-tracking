<?php

return [
    'name'        => 'Jotaworks Email Tracking',
    'description' => 'Plugin which selective prevents tracking in emails',
    'version'     => '1.2',
    'author'      => 'Jotaworks',
    'services' => [
        'events' => [
            'jw.mautic.email.tracking.subscriber' => [
                'class' => \MauticPlugin\JotaworksEmailTrackingBundle\EventListener\BuilderSubscriber::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'mautic.email.model.email',
                    'mautic.page.model.trackable',
                    'mautic.page.model.redirect',
                    'translator',
                    'doctrine.orm.entity_manager',
                    'jw.emailtracking.settings'
                ],
                // M5: event subscribers must be tagged explicitly (M4 auto-tagged the 'events' section).
                'tags' => ['kernel.event_subscriber'],
            ]
        ],
        'integrations' => [
            'mautic.integration.emailtracking' => [
                'class'     => \MauticPlugin\JotaworksEmailTrackingBundle\Integration\EmailTrackingIntegration::class,              
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'session',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc'
                ],
            ]
        ],  
        'others'=> [
            'jw.emailtracking.settings' => [
                'class'     => \MauticPlugin\JotaworksEmailTrackingBundle\Integration\EmailTrackingSettings::class,
                'arguments' => [
                    'mautic.helper.integration'
                ],
            ],
        ],                            
        'helpers' => [        
        ]        
    ]   
];
