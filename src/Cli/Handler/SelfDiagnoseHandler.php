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
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;

final class SelfDiagnoseHandler
{
    private $style;
    private $config;
    private $git;
    private $github;

    /**
     * @var CliProcess
     */
    private $process;

    public function __construct(
        SymfonyStyle $style,
        Config $config,
        Git $git,
        GitHub $github,
        CliProcess $process
    ) {
        $this->style = $style;
        $this->config = $config;
        $this->git = $git;
        $this->github = $github;
        $this->process = $process;
    }

    public function handle(): void
    {
        $this->style->title('HubKit diagnoses');

        $version = $this->getGitVersion();
        $table = new StatusTable($this->style);

        if (version_compare($version, '2.10.0', 'lt')) {
            $table->addRow('Git version', 'failure', sprintf('Git version "%s" should be upgraded to at least 2.10.0', $version));
        } else {
            $table->addRow('Git version', 'success', $version);
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

        if (false !== $editor = getenv('EDITOR')) {
            $table->addRow('EDITOR configured', 'success', $editor);
        } else {
            $table->addRow('EDITOR configured', 'warning', 'The EDITOR environment variable should be set');
        }

        $this->testGitHubConfigurations($table);
        $this->testUpstreamRemoteSet($table);
        $this->testExecutableFound(
            $table,
            'splitsh-lite',
            'Unable to find splitsh-lite in your PATH. Must be set for the `split-repo` command'
        );
        $table->render();

        if ($table->hasStatus('error')) {
            $this->style->error('Please fix the reported errors.');
        } else {
            $this->style->success('All seems to be good.');
        }
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
        $result = (string) $this->git->getGitConfig($config, 'global');
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

    private function testUpstreamRemoteSet(StatusTable $table): void
    {
        if (! $this->git->isGitDir()) {
            return;
        }

        $result = $this->git->getGitConfig('remote.upstream.url');
        $label = 'Git remote "upstream" configured';

        if ($result !== '') {
            $table->addRow($label, 'success', $result);
        } else {
            $table->addRow($label, 'failure', 'Git remote "upstream" should be configured');
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
