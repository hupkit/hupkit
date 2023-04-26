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

use HubKit\Config;
use HubKit\Service\BranchSplitsh;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;

final class SplitRepoHandler extends GitBaseHandler
{
    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        Config $config,
        private readonly BranchSplitsh $branchSplitsh
    ) {
        parent::__construct($style, $git, $github, $config);
    }

    public function handle(Args $args): void
    {
        $this->git->guardWorkingTreeReady();
        $this->git->remoteUpdate('upstream');

        $branch = $this->getBranchName($args);

        $this->style->title('Repository Split');
        $this->informationHeader($branch);

        if ($args->getOption('dry-run')) {
            if ($this->branchSplitsh->drySplitBranch($branch) > 0) {
                $this->style->success('[DRY-RUN] Repository directories were split into there destination.');
            }

            return;
        }

        if (\count($this->branchSplitsh->splitBranch($branch)) > 0) {
            $this->style->success('Repository directories were split into there destination.');
        }
    }

    private function getBranchName(Args $args): string
    {
        $branch = $args->getArgument('branch');

        if ($branch === null) {
            return $this->git->getActiveBranchName();
        }

        $this->git->checkoutRemoteBranch('upstream', $branch);

        return $branch;
    }
}
