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

use Github\Exception\RuntimeException as GitHubRuntimeException;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Webmozart\Console\Api\Args\Args;

class SplitCreatedHandler extends GitBaseHandler
{
    public function handle(Args $args): void
    {
        $this->git->guardWorkingTreeReady();
        $this->git->remoteUpdate('upstream');

        $this->style->title('Repository Split Create');
        $this->informationHeader();

        $isPrivate = $this->github->getRepoInfo($this->github->getOrganization(), $this->github->getRepository())['private'];

        /** @var array<string, GitHub> $hosts */
        $hosts = [];
        $hosts[$this->github->getHostname()] = $this->github;

        if ($isPrivate) {
            $this->style->note('The main repository is private, split repositories will be created as private.');
        }

        $splits = $this->getSplits();

        if (\count($splits) === 0) {
            $this->style->block('No repository splits found, or splits are disabled for all branches.', 'INFO', 'fg=black;bg=yellow', ' ', true);

            return;
        }

        foreach ($splits as $url) {
            ['host' => $host, 'org' => $org, 'repo' => $repo] = Git::getGitUrlInfo($url);

            if (! isset($hosts[$host])) {
                $hosts[$host] = $this->github->createForHost($host);
            }

            $this->createRepository($hosts[$host], $org, $repo, public: ! $isPrivate);
        }

        $this->style->success('Repository splits were created.');
    }

    /**
     * @return array<string, string>
     */
    private function getSplits(): array
    {
        $repositories = [];

        /** @var array<string, array{'split': array<string, array{'url': string|bool}>}|array> $config */
        foreach ($this->config->getForRepository($this->github->getHostname(), $this->github->getOrganization() . '/' . $this->github->getRepository())['branches'] ?? [] as $config) {
            if (! isset($config['split']) || \count($config['split']) === 0) {
                continue;
            }

            foreach ($config['split'] as $split) {
                if ($split['url'] !== false) {
                    $repositories[$split['url']] = $split['url'];
                }
            }
        }

        return $repositories;
    }

    private function createRepository(GitHub $gitHub, string $org, string $repo, bool $public): void
    {
        try {
            $url = $gitHub->getRepoInfo($org, $repo)['html_url'];

            $this->style->writeln(sprintf('<fg=yellow> [INFO] Repository %s already exists.</>', OutputFormatter::escape($url)));
        } catch (GitHubRuntimeException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }

            $url = $gitHub->createRepo($org, $repo, public: $public, hasIssues: false)['html_url'];

            $this->style->writeln(sprintf('<fg=green> [OK] Repository %s was created.</>', OutputFormatter::escape($url)));
        }
    }
}
