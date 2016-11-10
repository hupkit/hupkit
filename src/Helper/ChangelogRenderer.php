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

namespace HubKit\Helper;

use HubKit\Service\Git;
use HubKit\StringUtil;
use HubKit\ThirdParty\GitHub;

class ChangelogRenderer
{
    private $git;
    private $github;

    public function __construct(Git $git, GitHub $github)
    {
        $this->git = $git;
        $this->github = $github;
    }

    public function renderChangelogOneLine(string $base, string $head): string
    {
        $url = 'https://'.$this->github->getHostname().$this->github->getOrganization().'/'.$this->github->getRepository();
        $changelog = '';

        foreach ($this->git->getLogBetweenCommits($base, $head) as $commit) {
            if ($this->extractInfoFromSubject($commit['subject'], $matches)) {
                $changelog .= $this->formatLine($matches, $url);
            }
        }

        return $changelog;
    }

    public function renderChangelogWithSections(string $base, string $head, bool $skipEmptySections = true): string
    {
        $url = 'https://'.$this->github->getHostname().'/'.$this->github->getOrganization().'/'.$this->github->getRepository();
        $changelog = '';

        foreach ($this->getSections($base, $head) as $section => $items) {
            if (!count($items)) {
                if (!$skipEmptySections) {
                    $changelog .= "### {$section}\n- nothing\n\n";
                }

                continue;
            }

            $changelog .= "### {$section}\n";

            foreach ($items as $item) {
                $changelog .= $this->formatLine($item, $url);
            }

            $changelog .= "\n";
        }

        return $changelog;
    }

    private function extractInfoFromSubject(string $subject, &$matches): bool
    {
        return 0 !== stripos($subject, 'Merge pull request #') &&
               preg_match('/^(?P<category>\w+) #(?P<number>\d+) (?P<title>.+?)$/', $subject, $matches);
    }

    private function formatLine(array $item, string $url): string
    {
        $title = $item['title'];
        $pos = mb_strrpos($title, '(');

        // Replace authors with links
        $title = mb_substr($title, 0, $pos).
            preg_replace(
                '#([\w\d-_]+)#',
                '[$1](https://'.$this->github->getHostname().'/$1)',
                mb_substr($title, $pos)
            )
        ;

        return sprintf('- %s [#%d](%s/issues/%2$d)', trim($title), $item['number'], $url)."\n";
    }

    private function getSections(string $base, string $head): array
    {
        $sections = [
            'Security' => [],
            'Added' => [],
            'Changed' => [],
            'Deprecated' => [],
            'Removed' => [],
            'Fixed' => [],
        ];

        foreach ($this->git->getLogBetweenCommits($base, $head) as $commit) {
            if (!$this->extractInfoFromSubject($commit['subject'], $matches)) {
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
