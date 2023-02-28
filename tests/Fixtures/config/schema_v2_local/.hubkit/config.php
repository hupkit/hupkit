<?php

return [
    'branches' => [
        ':default' => [],
        '2.0' => [
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
];
