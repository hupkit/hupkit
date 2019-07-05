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
use HubKit\Helper\ChangelogRenderer;
use HubKit\Service\CliProcess;
use HubKit\Service\Editor;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use HubKit\Service\ReleaseHooks;
use HubKit\Service\SplitshGit;
use HubKit\StringUtil;
use Rollerworks\Component\Version\ContinuesVersionsValidator;
use Rollerworks\Component\Version\Version;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class ReleaseHandler extends GitBaseHandler
{
    private $process;
    private $editor;
    private $config;
    private $splitshGit;
    /** @var IO */
    private $io;
    private $releaseHooks;

    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        CliProcess $process,
        Editor $editor,
        Config $config,
        SplitshGit $splitshGit,
        ReleaseHooks $releaseHooks
    ) {
        parent::__construct($style, $git, $github);
        $this->process = $process;
        $this->editor = $editor;
        $this->config = $config;
        $this->splitshGit = $splitshGit;
        $this->releaseHooks = $releaseHooks;
    }

    public function handle(Args $args, IO $io)
    {
        $branch = $this->git->getActiveBranchName();

        $this->io = $io;
        $this->informationHeader($branch);

        $version = $this->validateVersion($args->getArgument('version'));
        $versionStr = (string) $version;

        $this->validateBranchCompatibility($branch, $version);
        $this->git->ensureBranchInSync('upstream', $branch);

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

        $this->releaseHooks->preRelease($version, $branch, $args->getOption('title'), $changelog);

        // Perform the sub-split and tagging first as it's easier to recover from split error
        // then being able to re-run the release command on the source repository.
        $this->tagSplitRepositories($branch, $versionStr);

        $this->process->mustRun(['git', 'tag', '-s', 'v'.$versionStr, '-m', 'Release '.$versionStr]);
        $this->process->mustRun(['git', 'push', 'upstream', 'v'.$versionStr]);

        $release = $this->github->createRelease('v'.$versionStr, $changelog, $args->getOption('pre-release'), $args->getOption('title'));

        $this->releaseHooks->postRelease($version, $branch, $args->getOption('title'), $changelog);

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

    private function validateVersion(string $providedVersion): Version
    {
        if (\in_array(strtolower($providedVersion), ['alpha', 'beta', 'rc', 'stable', 'major', 'minor', 'next', 'patch'], true)) {
            $version = Version::fromString($this->git->getLastTagOnBranch())->getNextIncreaseOf(strtolower($providedVersion));
        } else {
            $version = Version::fromString($providedVersion);
        }

        $this->style->text('Provided version: '.$version);

        $tags = StringUtil::splitLines($this->process->mustRun('git tag --list')->getOutput());
        $this->guardTagDoesNotExist($version, $tags);

        if (!$this->io->isInteractive()) {
            return $version;
        }

        $validator = new ContinuesVersionsValidator(...array_map([Version::class, 'fromString'], $tags));

        if (!$validator->isContinues($version)) {
            $this->style->warning(
                [
                    'It appears there is a gap compared to the last version.',
                    'Expected one of: '.implode(', ', $validator->getPossibleVersions()),
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

    private function guardTagDoesNotExist(Version $version, array $tags)
    {
        $tags = array_map(
            function ($tag) {
                return ltrim($tag, 'vV');
            },
            $tags
        );

        if (!\in_array((string) $version, $tags, true)) {
            return;
        }

        $validator = new ContinuesVersionsValidator(...array_map([Version::class, 'fromString'], $tags));
        $validator->isContinues($version);

        $suggested = array_filter(
            $validator->getPossibleVersions(),
            function (Version $version) use ($tags) {
                return !\in_array((string) $version, $tags, true);
            }
        );

        throw new \RuntimeException(
            sprintf(
                'Tag for version "v%s" already exists, did you mean: v%s ?',
                (string) $version,
                implode(', v', $suggested)
            )
        );
    }

    private function tagSplitRepositories(string $branch, string $version): void
    {
        $configName = ['repos', $this->github->getHostname(), $this->github->getOrganization().'/'.$this->github->getRepository()];
        $reposConfig = $this->config->get($configName);

        if (empty($reposConfig['split'])) {
            return;
        }

        $this->splitshGit->checkPrecondition();

        $this->style->text('Starting split operation please wait...');
        $progressBar = $this->style->createProgressBar();
        $progressBar->start(\count($reposConfig['split']));

        $splits = [];

        foreach ($reposConfig['split'] as $prefix => $config) {
            $progressBar->advance();
            $split = $this->splitshGit->splitTo($branch, $prefix, \is_array($config) ? $config['url'] : $config);

            if ($split !== null && ($config['sync-tags'] ?? $reposConfig['sync-tags'] ?? true)) {
                $splits += $split;
            }
        }

        $this->splitshGit->syncTags($version, $branch, $splits);
    }
}
