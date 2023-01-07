<?php

declare(strict_types=1);

namespace TypesenseSearch;

use Laminas\Router\Http\Literal;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],

    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],

    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'search' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/search',
                            'defaults' => [
                                '__NAMESPACE__' => 'TypesenseSearch\Controller',
                                'controller' => Controller\SearchController::class, // unique name
                                'action'     => 'search',
                            ],
                        ],
                    ]
                ]
            ],
        ],
    ],

    'controllers' => [
        'factories' => [
            Controller\SearchController::class => Service\Controller\SearchControllerFactory::class,
        ]
    ],

    'typesensesearch' => [
        'config' => [
            'typesense_host' => null,
            'typesense_protocol' => null,
            'typesense_port' => null,
            'typesense_api_key' => null,
        ],
    ],
];
