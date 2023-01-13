<?php

declare(strict_types=1);

namespace TypesenseSearch;

use Laminas\Router\Http\Literal;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            # makes it possible to return json in api response.
            'ViewJsonStrategy',
        ],
    ],

    'form_elements' => [
        'invokables' => [
            # module configuration form
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
                                # attach SearchController to /search route with default action as `search`
                                'controller' => Controller\SearchController::class,
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
            # Since settings cannot be accessed in a regular AbstractActionController, we create this instance using a factory class.
            Controller\SearchController::class => Service\Controller\SearchControllerFactory::class,
        ]
    ],

    'typesensesearch' => [
        'config' => [
            'typesense_host' => null,
            'typesense_protocol' => null,
            'typesense_port' => null,
            'typesense_api_key' => null,
            'typesense_search_index' => null,
        ],
    ],
];
