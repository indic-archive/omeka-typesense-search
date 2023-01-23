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
            'admin' => [
                'child_routes' => [
                    'create-index' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/search-index/create',
                            'defaults' => [
                                '__NAMESPACE__' => 'TypesenseSearch\Controller',
                                'controller' => Controller\SearchController::class,
                                'action' => 'createIndex',
                            ],
                        ],
                    ],
                    'drop-index' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/search-index/drop',
                            'defaults' => [
                                '__NAMESPACE__' => 'TypesenseSearch\Controller',
                                'controller' => Controller\SearchController::class,
                                'action' => 'dropIndex',
                            ],
                        ],
                    ],
                    'recreate-index' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/search-index/recreate',
                            'defaults' => [
                                '__NAMESPACE__' => 'TypesenseSearch\Controller',
                                'controller' => Controller\SearchController::class,
                                'action' => 'recreateIndex',
                            ],
                        ],
                    ],
                ],
            ],

            'site' => [
                'type' => \Laminas\Router\Http\Segment::class,
                'options' => [
                    'route' => '/:site-slug',
                    'constraints' => [
                        'site-slug' => '[a-zA-Z0-9_-]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Omeka\Controller\Site',
                        '__SITE__' => true,
                        'controller' => 'Index',
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => true,
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
            'typesense_url' => null,
            'typesense_api_key' => null,
            'typesense_search_index' => null,
            'typesense_index_properties' => [],
            'typesense_search_result_format' => '',
            'typesense_search_result_format_fallback' => '',
        ],
    ],
];
