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
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Adapter\ArgsInput;
use Webmozart\Console\Adapter\IOOutput;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class PullRequestMergeHandler extends GitBaseHandler
{
    private $config;

    public function __construct(SymfonyStyle $style, Git $git, Config $config, GitHub $github)
    {
        parent::__construct($style, $git, $github);
        $this->config = $config;
    }

    public function handle(Args $args, IO $io)
    {
        $pr = $this->github->getPullRequest(
            $args->getArgument('number'),
            true
        );

        $this->informationHeader($pr['base']['ref']);
        $this->style->writeln(
            [
                sprintf('Merging Pull Request <fg=yellow>%d: %s</>', $pr['number'], $pr['title']),
                '<fg=yellow>'.$pr['html_url'].'</>',
                '',
            ]
        );

        $this->guardMergeStatus($pr);
        $this->renderStatus($pr);

        $helper = new \HubKit\Helper\SingleLineChoiceQuestionHelper();
        $helper->ask(
            new ArgsInput($args->getRawArgs(), $args),
            new IOOutput($io),
            new ChoiceQuestion(
                'Category', [
                    'feature' => 'feature',
                    'bug' => 'bug',
                    'minor' => 'minor',
                    'style' => 'style',
                    // 'security' => 'security', // (special case needs to be handled differently)
                ]
            )
        );
    }

    private function guardMergeStatus(array $pr)
    {
        if ('closed' === $pr['state']) {
            throw new \InvalidArgumentException('Cannot merge closed pull-request.');
        }

        if (null === $pr['mergeable']) {
            throw new \InvalidArgumentException(
                'Pull-request is not processed yet. Please try again in a few seconds.'
            );
        }

        if (true === $pr['mergeable']) {
            return;
        }

        throw new \InvalidArgumentException('Pull-request has conflicts which need to be resolved first.');
    }

    private function renderStatus(array $pr)
    {
        $status = $this->github->getCommitStatuses(
            $pr['base']['user']['login'],
            $pr['base']['repo']['name'],
            $pr['head']['sha']
        );

        if ('pending' === $status['state']) {
            $this->style->warning('Status checks are pending, merge with caution.');

            return;
        }

        $table = new StatusTable($this->style);

        foreach ($status['statuses'] as $statusItem) {
            $label = explode('/', $statusItem['context']);
            $label = ucfirst($label[1] ?? $label[0]);

            $table->addRow($label, $statusItem['state'], $statusItem['description']);
        }

        $this->determineReviewStatus($pr, $table);
        $table->render();

        if ($table->hasStatus('error') || $table->hasStatus('pending') || $table->hasStatus('failure')) {
            $this->style->warning('One or more status checks did not complete or failed. Merge with caution.');
        }
    }

    private function determineReviewStatus(array $pr, StatusTable $table)
    {
        if (!count($pr['labels'])) {
            return;
        }

        $expects = [
            'ready' => 'success',
            'status: reviewed' => 'success',
            'status: ready' => 'success',
            'status: needs work' => 'failure',
            'status: needs review' => 'pending',
        ];

        foreach ($pr['labels'] as $label) {
            $name = strtolower($label['name']);

            if (isset($expects[$name])) {
                $table->addRow('Reviewed', $expects[$name], $label['name']);

                return;
            }
        }
    }
}
