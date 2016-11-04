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
use HubKit\Helper\StatusTableRenderer;
use HubKit\Service\Git;
use HubKit\ThirdParty\GitHub;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class DiagnoseHandler
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

        $rows = [];
        $errors = [];

        $rows[] = $this->testConfiguration('Git user.name configured', function () {
            return '' !== (string) $this->git->getGitConfig('user.name', 'global');
        }, $errors);

        $rows[] = $this->testConfiguration('Git user.email configured', function () {
            return '' !== (string) $this->git->getGitConfig('user.email', 'global');
        }, $errors);

        $rows[] = $this->testConfiguration('Git user.signingkey configured', function () {
            if ('' === (string) $this->git->getGitConfig('user.signingkey', 'global')) {
                return 'user.signingkey must be configured if you want to create a new release';
            }

            return true;
        }, $errors);

        $this->testGitHubConfigurations($rows, $errors);

        StatusTableRenderer::renderTable($this->style, $rows);

        if ($errors) {
            $this->style->error('Please fix the reported errors.');
        } else {
            $this->style->success('All seems to be good.');
        }
    }

    private function testGitHubConfigurations(array &$result, &$errors)
    {
        foreach ($this->config->get('github', []) as $hostname => $authentication) {
            $this->github->initializeForHost($hostname);

            $result[] = $this->testConfiguration(
                sprintf('GitHub "%s" authentication', $hostname),
                function () {
                    try {
                        return $this->github->isAuthenticated();
                    } catch (\Exception $e) {
                        return get_class($e).': '.$e->getMessage();
                    }
                },
                $errors
            );
        }
    }

    private function testConfiguration(string $label, \Closure $expectation, &$errors)
    {
        $result = $expectation();

        if (true === $result) {
            return [$label, StatusTableRenderer::renderLabel('success'), ''];
        }

        $errors = true;

        return [$label, StatusTableRenderer::renderLabel('failure'), wordwrap((string) $result, 38)];
    }
}
