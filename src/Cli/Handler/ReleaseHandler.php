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
use HubKit\Service\Editor;
use HubKit\Service\Git;
use HubKit\StringUtil;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class ReleaseHandler extends GitBaseHandler
{
    private $process;
    private $editor;
    /** @var IO */
    private $io;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github, CliProcess $process, Editor $editor)
    {
        parent::__construct($style, $git, $github);
        $this->process = $process;
        $this->editor = $editor;
    }

    public function handle(Args $args, IO $io)
    {
        $this->io = $io;
        $this->informationHeader();

        $version = $this->validateVersion($args->getArgument('version'));
        $branch = $this->git->getActiveBranchName();
        $versionStr = (string) $version;

        $this->validateBranchCompatibility($branch, $version);

        $this->style->writeln(
            [
                sprintf(
                    '<fg=cyan>Preparing release</> <fg=yellow>%s</> <fg=cyan>(target branch</> <fg=yellow>%s</><fg=cyan>)</>',
                    $versionStr,
                    $branch
                ),
                'Please wait...',
            ]
        );

        $changelog = $this->getChangelog($branch);

        if (!$args->getOption('no-edit')) {
            $changelog = $this->editor->fromString(
                $changelog,
                true,
                sprintf('Release "%s" for branch "%s". Leave file empty to abort operation.', $versionStr, $branch)
            );
        }

        $this->process->mustRun(['git', 'tag', '-s', $versionStr, '-m', 'Release '.$versionStr]);
        $this->process->mustRun(['git', 'push', '--tags', 'upstream']);

        $release = $this->github->createRelease($versionStr, $changelog, $args->getOption('pre-release'));
        $this->style->success([sprintf('Successfully released %s', $versionStr), $release['html_url']]);
    }

    private function validateBranchCompatibility(string $branch, Version $version)
    {
        if (!$this->io->isInteractive()) {
            return;
        }

        if ($branch === $version->major.'.'.$version->minor) {
            return;
        }

        if ($this->git->remoteBranchExists('upstream', $expected = $version->major.'.'.$version->minor)) {
            $this->style->warning(
                [
                    sprintf('This release will be created for the "%s" branch.', $branch),
                    sprintf(
                        'But a branch with version pattern "%s" exists, did you target the correct branch?',
                        $expected
                    ),
                    'If this is incorrect, checkout the correct version branch first.',
                ]
            );

            $this->confirmPossibleError();

            return;
        }
    }

    private function validateVersion(string $version): Version
    {
        $version = Version::fromString($version);

        if (!$this->io->isInteractive()) {
            return $version;
        }

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

    private function getChangelog(string $branch): string
    {
        try {
            $base = $this->git->getLastTagOnBranch();
        } catch (\Exception $e) {
            // No tags exist yet so there is no need for a changelog.
            $base = null;
        }

        if (null !== $base) {
            return (new ChangelogRenderer($this->git, $this->github))->renderChangelogByCategories($base, $branch);
        }

        return 'Initial release.';
    }
}
