<?php

declare(strict_types=1);

/*
 * This file is part of the HubKit package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
                        'x.0' => [ // Not supported, too uncommon. Use regexp instead.
                            'split' => [
                                'doc' => [
                                    'url' => 'git@github.com:park-manager/doc.git',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
