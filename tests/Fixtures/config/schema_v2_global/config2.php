<?php

return [
    'schema_version' => 2,
    'github' => [
        'github.com' => [
            'username' => 'test',
            'api_token' => 'test-token',
        ],
    ],
    'repositories' => [
        'github.com' => [
            'repos' => [
                'park-manager/park-manager' => [
                    'branches' => [
                        ':default' => [
                            'sync-tags' => true,
                            'split' => [
                                'src/Bundle/CoreBundle' => [
                                    'url' => 'git@github.com:park-manager/core-bundle.git',
                                ],
                                'src/Bundle/UserBundle' => [
                                    'url' => 'git@github.com:park-manager/user-bundle.git',
                                    'sync-tags' => null,
                                ],
                                'doc' => [
                                    'url' => 'git@github.com:park-manager/doc.git',
                                    'sync-tags' => false,
                                ],
                            ],
                            'upmerge' => true,
                        ],

                        // Additional branch names for testing
                        'main' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git']]],
                        'master' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git']]],
                        '0.1' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git']]],
                        '1.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git']]],
                        '2.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git']]],
                        '3.x' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git']]],

                        // Pattern
                        '4.*' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git']]],

                        // Regexp (without anchors and options)
                        '/[1-5]\.[0-9]/' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/brown.git']]],

                        '10.0' => false,
                    ],
                ],
            ],
        ],
    ],
];
