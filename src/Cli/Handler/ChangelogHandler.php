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

use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\StringUtil;
use HubKit\ThirdParty\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class ChangelogHandler extends GitBaseHandler
{
    private $process;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github, CliProcess $process)
    {
        parent::__construct($style, $git, $github);
        $this->process = $process;
    }

    public function handle(Args $args, IO $io)
    {
        if (!($ref = $args->getArgument('ref'))) {
            $base = $this->git->getLastTagOnBranch();
            $head = $this->git->getActiveBranchName();
        } else {
            list($base, $head) = $this->getRefRange($ref);
        }

        $this->informationHeader($head);

        $io->writeLine($this->renderChangelog($base, $head));
    }

    private function getRefRange(string $ref): array
    {
        if (false === strpos($ref, '..', 1) || 2 !== count($points = explode('..', $ref))) {
            throw new \InvalidArgumentException('missing ref range `base..head` or illegal offset given');
        }

        return $points;
    }

    // To be moved to ChangelogRenderer

    private function renderChangelog(string $base, string $head, bool $skipEmptySections = true): string
    {
        $url = 'https://'.$this->github->getOrganization().'/'.$this->github->getRepository();
        $changelog = '';

        foreach ($this->getSections($base, $head) as $section => $items) {
            if ($skipEmptySections && !count($items)) {
                continue;
            }

            $changelog .= '### '.$section."\n";

            foreach ($items as $item) {
                $changelog .= sprintf('- %s [#%d](%s/issues/%2$d)', $item['title'], $item['number'], $url)."\n";
            }

            $changelog .= "\n";
        }

        return $changelog;
    }

    private function getSections(string $base, string $head): array
    {
        $sections = [
            'Security' => [],
            'Added' => '',
            'Changed' => [],
            'Deprecated' => [],
            'Removed' => [],
            'Fixed' => [],
        ];

        foreach ($this->git->getLogBetweenCommits($base, $head) as $commit) {
            if (0 === stripos($commit['subject'], 'Merge pull request #') ||
                !preg_match('/^(?P<category>\w+) #(?P<number>\d+) (?P<title>[^$]+)/', $commit['subject'], $matches)
            ) {
                continue;
            }

            $section = $this->getSectionForCommit($commit + $matches);
            $sections[$section][] = $matches;
        }

        return $sections;
    }

    private function getSectionForCommit(array $commit): string
    {
        // Security can only ever be related about security.
        if ('security' === $commit['category']) {
            return 'Security';
        }

        list(, $labelsStr) = StringUtil::splitLines(ltrim($commit['message']));

        $catToSection = [
            'feature' => 'Added',
            'refactor' => 'Changed',
            'bug' => 'Fixed',
        ];

        // Detect labels eg. `labels: deprecation`
        if (0 === strpos($labelsStr, 'labels: ')) {
            $labels = array_map('trim', explode(', ', substr($labelsStr, 8)));

            if (in_array('deprecation', $labels, true)) {
                return 'Deprecated';
            }

            if (in_array('removed-deprecation', $labels, true)) {
                return 'Removed';
            }
        }

        return $catToSection[$commit['category']] ?? 'Changed';
    }
}
