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

use HubKit\Helper\ChangelogRenderer;
use HubKit\Helper\Version;
use HubKit\Helper\VersionsValidator;
use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\StringUtil;
use HubKit\ThirdParty\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class ReleaseHandler extends GitBaseHandler
{
    private $process;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github, CliProcess $process)
    {
        parent::__construct($style, $git, $github);
        $this->process = $process;
    }

    public function handle(Args $args, IO $io)
    {
        $this->informationHeader();

        $version = $this->validateVersion($args->getArgument('version'));
        $branch = $this->git->getActiveBranchName();

        // XXX Check if version matches expected branch, 1.0 for master while 1.0 branch exists.

        $io->writeLine((string) $version);

        $base = $this->getFirstCommit($branch);
        $changelog = (new ChangelogRenderer($this->git, $this->github))->renderChangelogWithSections($base, $branch);

        $io->writeLine($changelog);
    }

    private function validateBranchCompatibility(string $branch, Version $version)
    {
        if ($branch === $version->major.'.'.$version->minor) {
            return true;
        }

        // $this->git->branchExists();


        if ('master' === $branch) {
            return true; // XXX Not actually correct, this should only be considered when there is no other branch
        }

    }

    private function validateVersion(string $version): Version
    {
        $version = Version::fromString($version);
        $tags = StringUtil::splitLines($this->process->mustRun('git tag --list')->getOutput());

        if (!VersionsValidator::isVersionContinues(VersionsValidator::getHighestVersions($tags), $version, $suggested)) {
            $this->style->warning(
                [
                    'It appears there is gap compared to the last version.',
                    'Expected one of : '.implode(', ', $suggested),
                ]
            );

            $this->confirmPossibleError();
        }

        return $version;
    }

    private function confirmPossibleError()
    {
        if (!$this->style->confirm('Please confirm your input is correct.', false)) {
            throw new \RuntimeException('User aborted.');
        }
    }

    private function getFirstCommit(string $branchName): string
    {
        try {
            return $this->git->getLastTagOnBranch();
        } catch (\Exception $e) {
            // No tags exist yet so use the first commit on this branch.
            //
            // This is basically the first commit every made! but should be no problem as
            // any branch after a tagged branch will have a tag, any orphan branch has it's own commits.
            // Only when the branch diverged this may fail, but you'd properly have bigger problems then.
            return $this->git->getFirstCommitOnBranch($branchName);
        }
    }
}
