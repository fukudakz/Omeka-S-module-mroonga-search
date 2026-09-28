<?php
namespace MroongaSearch;

return [
    'omeka_version_constraint' => '^4.0.0',
    'psr-4' => [
        'MroongaSearch\\' => __DIR__ . '/../src',
    ],
    'controllers' => [
        'factories' => [
            'MroongaSearch\Controller\SearchController' => 'MroongaSearch\Controller\SearchControllerFactory',
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'mroonga-search' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/mroonga-search',
                            'defaults' => [
                                '__NAMESPACE__' => 'MroongaSearch\Controller',
                                'controller' => 'SearchController',
                                'action' => 'search',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'enhanced' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/enhanced',
                                    'defaults' => [
                                        'action' => 'enhancedSearch',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'MroongaSearch\Service\MroongaSearchService' => 'MroongaSearch\Service\MroongaSearchServiceFactory',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'mroongaSearch' => 'MroongaSearch\View\Helper\MroongaSearchHelperFactory',
        ],
    ],
];

