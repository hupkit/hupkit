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
use HubKit\Helper\StatusTable;
use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\Service\Git\GitFileReader;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\VarExporter\VarExporter;

final class SelfDiagnoseHandler
{
    public function __construct(
        private readonly SymfonyStyle $style,
        private readonly Config $config,
        private readonly Git $git,
        private readonly GitHub $github,
        private readonly CliProcess $process,
        private readonly GitFileReader $gitFileReader
    ) {
    }

    public function handle(): int
    {
        $this->style->title('HubKit Diagnoses');

        $version = $this->getGitVersion();
        $table = new StatusTable($this->style);

        if (version_compare($version, '2.10.0', 'lt')) {
            $table->addRow('Git version', 'failure', sprintf('Git version "%s" should be upgraded to at least 2.10.0', $version));
        } else {
            $table->addRow('Git version', 'success', $version);
        }

        if (! $this->git->isGitDir()) {
            $this->style->text('<fg=yellow>💡 Run this command in a Git repository for more information.</>');
            $this->style->newLine();
        }

        $this->testRequiredGitConfig($table, 'user.name');
        $this->testRequiredGitConfig($table, 'user.email');
        $this->testOptionalGitConfig(
            $table,
            'user.signingkey',
            'Must be configured for the `release` command'
        );
        $this->testOptionalGitConfig(
            $table,
            'gpg.program',
            'No gpg program configured. Must be configured for the `release` command'
        );
        $this->testAdvisedGitConfigValue(
            $table,
            'commit.gpgsign',
            'true',
            'Commit signing is not enabled. Set "commit.gpgsign" to true'
        );

        if (\defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $this->testAdvisedGitConfigValue(
                $table,
                'core.eol',
                'lf',
                'This is known to cause problems on Windows. Should be set to "lf", is "%s"'
            );
        }

        $editor = getenv('EDITOR');

        if ($editor !== false) {
            $table->addRow('EDITOR configured', 'success', $editor);
        } else {
            $table->addRow('EDITOR configured', 'warning', 'The EDITOR environment variable should be set');
        }

        $this->testGitHubConfigurations($table);
        $this->testExecutableFound(
            $table,
            'splitsh-lite',
            'Unable to find splitsh-lite in your PATH. Must be set for the `split-repo` command'
        );

        try {
            $this->github->autoConfigure($this->git);
        } catch (\Exception) {
        }

        $this->testUpstreamRemoteSet($table);
        $this->testConfigFilePresence($table);
        $table->render();

        if ($table->hasStatus('error')) {
            $this->style->error('Please fix the reported errors.');
        } else {
            $this->style->success('All seems to be good.');
        }

        if ($this->github->getOrganization() !== '') {
            $this->style->comment('Repository Configuration');
            $this->style->text(
                explode(
                    "\n",
                    VarExporter::export(
                        $this->config->getForRepository(
                            $this->github->getHostname(),
                            sprintf('%s/%s', $this->github->getOrganization(), $this->github->getRepository())
                        )
                    )
                )
            );
        }

        return $table->hasStatus('failure') ? 1 : 0;
    }

    private function testGitHubConfigurations(StatusTable $table): void
    {
        foreach ($this->config->get('github', []) as $hostname => $authentication) {
            $label = sprintf('GitHub "%s" authentication', $hostname);

            try {
                $this->github->initializeForHost($hostname);
                $table->addRow($label, $this->github->isAuthenticated() ? 'success' : 'failure', $authentication['username']);
            } catch (\Exception $e) {
                $table->addRow($label, 'failure', $e::class . ': ' . $e->getMessage());
            }
        }
    }

    private function testRequiredGitConfig(StatusTable $table, string $config): void
    {
        $result = $this->git->getGitConfig($config, 'global');
        $label = sprintf('Git "%s" configured', $config);

        if ($result !== '') {
            $table->addRow($label, 'success', $result);
        } else {
            $table->addRow($label, 'failure', sprintf('Missing "%s" in global Git config', $config));
        }
    }

    private function testAdvisedGitConfigValue(StatusTable $table, string $config, string $expected, string $message): void
    {
        $result = $this->git->getGitConfig($config, 'global');
        $label = sprintf('Git "%s" configured', $config);

        if ($expected === $result) {
            $table->addRow($label, 'success', $result);
        } else {
            $table->addRow($label, 'warning', str_contains($message, '%s') ? sprintf($message, $result) : $message);
        }
    }

    private function testOptionalGitConfig(StatusTable $table, string $config, string $message): void
    {
        $result = $this->git->getGitConfig($config, 'global');
        $label = sprintf('Git "%s" configured', $config);

        if ($result !== '') {
            $table->addRow($label, 'success', $result);
        } else {
            $table->addRow($label, 'warning', $message);
        }
    }

    private function testExecutableFound(StatusTable $table, string $executable, string $message): void
    {
        $finder = new ExecutableFinder();
        $result = $finder->find($executable, '');
        $label = sprintf('Executable "%s" found in PATH', $executable);

        if ($result !== '') {
            $table->addRow($label, 'success', $result);
        } else {
            $table->addRow($label, 'warning', str_contains($message, '%s') ? sprintf($message, $result) : $message);
        }
    }

    private function testUpstreamRemoteSet(StatusTable $table): void
    {
        $label = 'Git remote "upstream" configured';

        if (! $this->git->isGitDir()) {
            $table->addRow($label, 'skipped', 'This is not a Git repository');

            return;
        }

        $result = $this->git->getGitConfig('remote.upstream.url');

        if ($result !== '') {
            $table->addRow($label, 'success', $result);
        } else {
            $table->addRow($label, 'failure', 'Git remote "upstream" should be configured');
        }
    }

    private function testConfigFilePresence(StatusTable $table): void
    {
        $label = 'Repository Configuration';

        if (! $this->git->isGitDir()) {
            $table->addRow($label, 'skipped', 'This is not a Git repository');

            return;
        }

        if ($this->git->getGitConfig('remote.upstream.url') === '' || $this->github->getHostname() === null) {
            $table->addRow($label, 'skipped', 'Unable to detect host and repository');

            return;
        }

        if ($this->git->branchExists('_hubkit')) {
            if (! $this->gitFileReader->fileExists('_hubkit', 'config.php')) {
                $table->addRow($label, 'failure', 'Branch "_hubkit" exists but config.php was not found');

                return;
            }

            if ($this->gitFileReader->fileExistsAtRemote('upstream', '_hubkit', 'config.php')) {
                $status = $this->git->getRemoteDiffStatus('upstream', '_hubkit');

                if ($status !== Git::STATUS_UP_TO_DATE) {
                    $table->addRow($label, 'warning',
                        sprintf('Branch "_hubkit" is diverged with upstream: %s%sRun sync-config to update', $status, "\n")
                    );

                    return;
                }
            }

            $table->addRow($label, 'success', 'Found config.php in branch "_hubkit"');

            return;
        }

        if ($this->git->remoteBranchExists('upstream', '_hubkit')) {
            if ($this->gitFileReader->fileExistsAtRemote('upstream', '_hubkit', 'config.php')) {
                $table->addRow($label, 'info', 'Found config.php in branch "_hubkit" at remote "upstream", but not local');
            } else {
                $table->addRow($label, 'failure', 'Branch "_hubkit" at upstream exists but config.php was not found');
            }

            return;
        }

        $table->addRow($label, 'skipped', 'Branch _hubkit" was neither found locally or at remote "upstream"');
    }

    private function getGitVersion(): string
    {
        return explode(
            ' ',
            trim(
                $this->process->mustRun(['git', '--version'], 'Git is not installed or PATH is not properly configured.')->getOutput()
            )
        )[2];
    }
}
