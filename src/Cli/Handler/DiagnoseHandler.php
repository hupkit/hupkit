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

        $result = [];
        $errors = [];

        $result[] = $this->testConfiguration('Git user.name configured', function () {
            return '' !== (string) $this->git->getGitConfig('user.name', 'global');
        }, $errors);

        $result[] = $this->testConfiguration('Git user.email configured', function () {
            return '' !== (string) $this->git->getGitConfig('user.email', 'global');
        }, $errors);

        $result[] = $this->testConfiguration('Git user.signingkey configured', function () {
            if ('' === (string) $this->git->getGitConfig('user.signingkey', 'global')) {
                return 'user.signingkey must be configured if you want to create a new release';
            }

            return true;
        }, $errors);

        $result[] = $this->testConfiguration('GitHub authentication', function () {
            try {
                return $this->github->isAuthenticated();
            } catch (\Exception $e) {
                return get_class($e).': '.$e->getMessage();
            }
        }, $errors);

        $table = new Table($this->style);
        $table->getStyle()
            ->setHorizontalBorderChar('-')
            ->setVerticalBorderChar(' ')
            ->setCrossingChar(' ')
        ;

        $table->setHeaders(['Item', 'Status']);
        $table->setRows($result);

        $table->render();
        $this->style->newLine();

        if ($errors) {
            $this->style->section('Please fix the following errors');
            $this->style->listing($errors);
        }
    }

    private function testConfiguration(string $label, \Closure $expectation, array &$errors)
    {
        $result = $expectation();

        if (true === $result) {
            return [$label, '<fg=green>OK</>'];
        }

        if ('' !== (string) $result) {
            $errors[] = $result;
        }

        return [$label, '<fg=red>FAIL</>'];
    }
}
