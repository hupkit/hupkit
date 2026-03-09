<?php

declare(strict_types=1);

/*
 * This file is part of the HuPKit package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace HubKit\Cli\Handler;

use HubKit\Config;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;

final class CleanupUnmaintainedHandler extends GitBaseHandler
{
    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        Config $config,
    ) {
        parent::__construct($style, $git, $github, $config);
    }

    public function handle(Args $args): void
    {
        $this->informationHeader();

        $defaultBranch = $this->config->getMainBranch();
        $unmaintainedBranches = [];

        foreach ($this->config->get(['_local', 'branches'], []) as $branch => $config) {
            if ($branch === ':default' || $branch === $defaultBranch) {
                continue;
            }

            if ($config['maintained'] === false && $this->git->branchExists($branch)) {
                $unmaintainedBranches[] = $branch;
            }
        }

        if ($unmaintainedBranches === []) {
            $this->style->success('No unmaintained branches found.');

            return;
        }

        $this->style->warning('The following local branches are unmaintained and can be deleted.' . "\n\n * " . implode("\n * ", $unmaintainedBranches));

        if (! $this->style->confirm('Delete these local branches now?', true)) {
            throw new \RuntimeException('User aborted.');
        }

        foreach ($unmaintainedBranches as $branch) {
            $this->style->note(\sprintf('Deleting branch "%s"...', $branch));
            $this->git->deleteBranchWithForce($branch);
        }

        $this->style->success('All unmaintained branches were deleted.');
    }
}
