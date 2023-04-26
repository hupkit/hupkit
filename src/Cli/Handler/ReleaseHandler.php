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
use HubKit\Service\BranchSplitsh;
use HubKit\Service\CliProcess;
use HubKit\Service\Editor;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use HubKit\Service\ReleaseHooks;
use HubKit\StringUtil;
use Rollerworks\Component\Version\ContinuesVersionsValidator;
use Rollerworks\Component\Version\Version;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class ReleaseHandler extends GitBaseHandler
{
    private IO $io;

    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        Config $config,
        private readonly CliProcess $process,
        private readonly Editor $editor,
        private readonly BranchSplitsh $branchSplitsh,
        private readonly ReleaseHooks $releaseHooks
    ) {
        parent::__construct($style, $git, $github, $config);
    }

    public function handle(Args $args, IO $io): void
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

        if (! $args->getOption('no-edit')) {
            $changelog = $this->editor->fromString(
                $changelog,
                true,
                sprintf('Release "%s" for branch "%s". Leave file empty to abort operation.', $versionStr, $branch)
            );
        }

        $this->releaseHooks->preRelease($version, $branch, $args->getOption('title'), $changelog);

        // Perform the sub-split and tagging first as it's easier to recover from split error
        // then being able to re-run the release command on the source repository.
        $this->branchSplitsh->syncTags($branch, $versionStr);

        $this->process->mustRun(['git', 'tag', '-s', 'v' . $versionStr, '-m', 'Release ' . $versionStr]);
        $this->process->mustRun(['git', 'push', 'upstream', 'v' . $versionStr]);

        $release = $this->github->createRelease('v' . $versionStr, $changelog, $args->getOption('pre-release'), $args->getOption('title'));

        $this->releaseHooks->postRelease($version, $branch, $args->getOption('title'), $changelog);

        $this->style->success([sprintf('Successfully released %s', $versionStr), $release['html_url']]);
    }

    private function validateBranchCompatibility(string $branch, Version $version): void
    {
        if (! $this->io->isInteractive()) {
            return;
        }

        if ($branch === $version->major . '.' . $version->minor) {
            return;
        }

        if ($this->git->remoteBranchExists('upstream', $expected = $version->major . '.' . $version->minor)) {
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
        }
    }

    private function validateVersion(string $providedVersion): Version
    {
        if (\in_array(mb_strtolower($providedVersion), ['alpha', 'beta', 'rc', 'stable', 'major', 'minor', 'next', 'patch'], true)) {
            $version = Version::fromString($this->git->getLastTagOnBranch())->getNextIncreaseOf(mb_strtolower($providedVersion));
        } else {
            $version = Version::fromString($providedVersion);
        }

        $this->style->text('Provided version: ' . $version);

        $tags = StringUtil::splitLines($this->process->mustRun(['git', 'tag', '--list'])->getOutput());
        $this->guardTagDoesNotExist($version, $tags);

        if (! $this->io->isInteractive()) {
            return $version;
        }

        $validator = new ContinuesVersionsValidator(...array_map([Version::class, 'fromString'], $tags));

        if (! $validator->isContinues($version)) {
            $this->style->warning(
                [
                    'It appears there is a gap compared to the last version.',
                    'Expected one of: ' . implode(', ', $validator->getPossibleVersions()),
                ]
            );

            $this->confirmPossibleError();
        }

        return $version;
    }

    private function confirmPossibleError(): void
    {
        if (! $this->style->confirm('Please confirm your input is correct.', false)) {
            throw new \RuntimeException('User aborted.');
        }
    }

    private function getChangelog(string $branch): string
    {
        try {
            $base = $this->git->getLastTagOnBranch();
        } catch (\Exception) {
            // No tags exist yet so there is no need for a changelog.
            $base = null;
        }

        if ($base !== null) {
            return (new ChangelogRenderer($this->git, $this->github))->renderChangelogByCategories($base, $branch);
        }

        return 'Initial release.';
    }

    /**
     * @param array<int, string> $tags
     */
    private function guardTagDoesNotExist(Version $version, array $tags): void
    {
        $tags = array_map(
            static fn ($tag) => ltrim($tag, 'vV'),
            $tags
        );

        if (! \in_array((string) $version, $tags, true)) {
            return;
        }

        $validator = new ContinuesVersionsValidator(...array_map([Version::class, 'fromString'], $tags));
        $validator->isContinues($version);

        $suggested = array_filter(
            $validator->getPossibleVersions(),
            static fn (Version $version) => ! \in_array((string) $version, $tags, true)
        );

        throw new \RuntimeException(
            sprintf(
                'Tag for version "v%s" already exists, did you mean: v%s ?',
                (string) $version,
                implode(', v', $suggested)
            )
        );
    }
}
