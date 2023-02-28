<?php

return [
    'schema_version' => 1,
    'github' => [
        'github.com' => [
            'username' => 'test',
            'api_token' => 'test-token',
        ],
    ],
    'repos' => [
        'github.com' => [
            'park-manager/park-manager' => [
                'sync-tags' => true,
                'split' => [
                    'src/Bundle/CoreBundle' => 'git@github.com:park-manager/core-bundle.git',
                    'src/Bundle/UserBundle' => 'git@github.com:park-manager/user-bundle.git',
                    'doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
                ],
            ],
        ],
    ],
];
