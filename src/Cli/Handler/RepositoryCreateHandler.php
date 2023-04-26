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

namespace HubKit\Cli\Handler;

use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;

final class RepositoryCreateHandler
{
    public function __construct(
        private readonly SymfonyStyle $style,
        private readonly Git $git,
        private readonly GitHub $github
    ) {
    }

    public function handle(Args $args): void
    {
        [$organization, $name] = explode('/', $args->getArgument('full-name'), 2);

        $repo = $this->github->createRepo(
            $organization,
            $name,
            ! $args->getOption('private'),
            ! $args->getOption('no-issues')
        );

        $this->style->success(
            [
                sprintf(
                    'Repository "%s/%s" was created.',
                    $organization,
                    $name
                ),
                'Git: ' . $repo['ssh_url'],
                'Web: ' . $repo['html_url'],
            ]
        );

        if ($this->git->isGitDir()) {
            return;
        }

        if ($this->style->confirm('Do you want to clone the new repository to this directory?', false)) {
            $this->git->clone($repo['ssh_url'], 'upstream');
        }
    }
}
