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

use HubKit\Cli\RequiresGitRepository;
use HubKit\Config;
use HubKit\Service\Git;
use HubKit\StringUtil;
use HubKit\ThirdParty\GitHub;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;

final class PullRequestMergeHandler implements RequiresGitRepository
{
    private $style;
    private $git;
    private $github;

    /**
     * @var Config
     */
    private $config;

    public function __construct(SymfonyStyle $style, Git $git, Config $config, GitHub $github)
    {
        $this->style = $style;
        $this->git = $git;
        $this->config = $config;
        $this->github = $github;
    }

    public function handle(Args $args)
    {
        $pr = $this->github->getPullRequest(
            $args->getArgument('number')
        );

        $this->guardMergeStatus($pr);

        $this->renderPrStatus($pr);

    }

    private function guardMergeStatus(array $pr)
    {
        if ('closed' === $pr['state']) {
            throw new \InvalidArgumentException('Cannot merge closed pull-request.');
        }

        if (null === $pr['mergeable']) {
            throw new \InvalidArgumentException(
                'Pull-request is not processed yet. Please try again in a few seconds.'
            );
        }

        if (true === $pr['mergeable']) {
            return;
        }

        if ($this->config->get(['github', 'username']) === $pr['head']['user']['login']) {
            throw new \InvalidArgumentException('Pull-request has conflicts, please update the pull-request.');
        }

        throw new \InvalidArgumentException('Pull-request has conflicts which need to be resolved first.');
    }

    private function renderPrStatus(array $pr)
    {
        $status = $this->github->getCommitStatuses(
            $pr['base']['user']['login'],
            $pr['base']['repo']['name'],
            $pr['head']['sha']
        );

        if ('pending' === $status['state']) {
            $this->style->warning('Status checks are pending, merge with caution.');

            return;
        }

        // XXX NTH Review status

        $success = true;
        $labels = [
            'success' => '<fg=green> ✔ </>',
            'pending' => '<fg=yellow> ? </>',
            'failure' => '<fg=red> × ️</>',
            'error' => '<fg=red> ! ️</>',
        ];

        $this->style->section('Pull request status');

        foreach ($status['statuses'] as $statusItem) {
            $label = explode('/', $statusItem['context']);

            $this->style->writeln(' '.$labels[$statusItem['state']].'  '.($label[1] ?? $label[0]));

            // XXX "description" show below status table
            if ($statusItem['state'] !== 'success') {
                $success = false;
            }
        }

        if (!$success) {
            $this->style->warning('One or more checks did not complete or failed. Merge with caution.');
        }
    }
}
