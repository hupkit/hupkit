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

    public function handle(Args $args): ?int
    {
        $this->git->guardWorkingTreeReady();
        $this->git->remoteUpdate(REMOTE_MAIN);

        $branch = $this->getBranchName($args);
        $prefix = $args->getOption('prefix');

        $this->style->title('Repository Split');
        $this->informationHeader($branch);

        $this->guardMaintained($branch);

        if ($prefix !== null) {
            return $this->splitPrefixOnly($branch, $prefix, $args->getOption('dry-run'));
        }

        if ($args->getOption('dry-run')) {
            if ($this->branchSplitsh->drySplitBranch($branch) > 0) {
                $this->style->success('[DRY-RUN] Repository directories were split into there destination.');
            }

            return null;
        }

        if (\count($this->branchSplitsh->splitBranch($branch)) > 0) {
            $this->style->success('Repository directories were split into there destination.');
        }

        return null;
    }

    private function getBranchName(Args $args): string
    {
        $branch = $args->getArgument('branch');

        if ($branch === null) {
            return $this->git->getActiveBranchName();
        }

        $this->git->checkoutRemoteBranch(REMOTE_MAIN, $branch);

        return $branch;
    }

    private function splitPrefixOnly(string $branch, string $prefix, bool $dryRun): ?int
    {
        try {
            if ($dryRun) {
                $this->branchSplitsh->drySplitAtPrefix($branch, $prefix);
                $this->style->success(\sprintf('[DRY-RUN] Repository directory "%s" was split into it\'s destination.', $prefix));

                return null;
            }

            if ($this->branchSplitsh->splitAtPrefix($branch, $prefix) !== null) {
                $this->style->success(\sprintf('Repository directory "%s" was split into it\'s destination.', $prefix));
            }

            return null;
        } catch (\InvalidArgumentException $e) {
            if ($e->getCode() !== 50) {
                throw $e;
            }

            $branchConfig = $this->config->getBranchConfig($branch);
            $found = null;

            /** @var string $p */
            foreach ($branchConfig->config['split'] ?? [] as $p => $d) {
                // See if we can find one with a different case matching.
                if (strcasecmp($p, $prefix) === 0) {
                    $found = $p;

                    break;
                }
            }

            $this->style->error($e->getMessage());
            $this->style->writeln(' The following prefixes are available for this branch:');
            $this->style->listing(array_keys($branchConfig->config['split']));

            if ($found !== null) {
                $this->style->info('A prefixes with a different casing was found.');

                if ($this->style->confirm(\sprintf('Did you mean "%s"?', $found))) {
                    return $this->splitPrefixOnly($branch, $found, $dryRun);
                }
            }

            return 1;
        }
    }
}
