<?php

return [
    'schema_version' => 1,
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
                    ],
                ],
            ],
        ],
    ],
];
