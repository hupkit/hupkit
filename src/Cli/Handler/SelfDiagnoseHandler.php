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
use HubKit\Service\Git;
use HubKit\ThirdParty\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class SelfDiagnoseHandler
{
    private $style;
    private $config;

    /**
     * @var Git
     */
    private $git;

    /**
     * @var GitHub
     */
    private $github;

    public function __construct(
        SymfonyStyle $style,
        Config $config,
        Git $git,
        GitHub $github
    ) {
        $this->style = $style;
        $this->config = $config;
        $this->git = $git;
        $this->github = $github;
    }

    public function handle(Args $args, IO $io)
    {
        $this->style->title('HubKit diagnoses');

        $table = new StatusTable($this->style);

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

        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $this->testAdvisedGitConfigValue(
                $table,
                'core.eol',
                'lf',
                'This is known to cause problems on Windows. Should be set to "lf", is "%s"'
            );
        }

        $this->testGitHubConfigurations($table);
        $table->render();

        if ($table->hasStatus('error')) {
            $this->style->error('Please fix the reported errors.');
        } else {
            $this->style->success('All seems to be good.');
        }
    }

    private function testGitHubConfigurations(StatusTable $table)
    {
        foreach ($this->config->get('github', []) as $hostname => $authentication) {
            $label = sprintf('GitHub "%s" authentication', $hostname);

            try {
                $this->github->initializeForHost($hostname);
                $table->addRow($label, $this->github->isAuthenticated() ? 'success' : 'failure');
            } catch (\Exception $e) {
                $table->addRow($label, 'failure', get_class($e).': '.$e->getMessage());
            }
        }
    }

    private function testRequiredGitConfig(StatusTable $table, string $config)
    {
        $result = (string) $this->git->getGitConfig($config, 'global');
        $label = sprintf('Git "%s" configured', $config);

        if ('' !== $result) {
            $table->addRow($label, 'success', $result);
        } else {
            $table->addRow($label, 'failure', sprintf('Missing "%s" in global Git config', $config));
        }
    }

    private function testAdvisedGitConfigValue(StatusTable $table, string $config, string $expected, string $message)
    {
        $result = (string) $this->git->getGitConfig($config, 'global');
        $label = sprintf('Git "%s" configured', $config);

        if ($expected === $result) {
            $table->addRow($label, 'success', $result);
        } else {
            $table->addRow($label, 'warning', strpos($message, '%s') !== false ? sprintf($message, $result) : $message);
        }
    }

    private function testOptionalGitConfig(StatusTable $table, string $config, string $message)
    {
        $result = (string) $this->git->getGitConfig($config, 'global');
        $label = sprintf('Git "%s" configured', $config);

        if ('' !== $result) {
            $table->addRow($label, 'success', $result);
        } else {
            $table->addRow($label, 'warning', $message);
        }
    }
}
