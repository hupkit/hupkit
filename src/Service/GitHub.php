<?php

declare(strict_types=1);

/*
 * This file is part of the HuPKit package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace HubKit\Service;

use Github\AuthMethod;
use Github\Client as GitHubClient;
use Github\HttpClient\Builder;
use Github\ResultPager;
use HubKit\Config;
use Psr\Http\Client\ClientInterface;

class GitHub
{
    final public const DEFAULT_HOST = 'github.com';

    private ?Builder $clientBuilder = null;
    private ?GitHubClient $client = null;

    private string $organization = '';
    private string $repository = '';
    private string $hostname = '';
    private string $username = '';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private Config $config
    ) {}

    public function autoConfigure(Git $git): void
    {
        $repo = $git->getRemoteInfo('upstream');

        if ($repo['org'] === '') {
            throw new \RuntimeException('Remote "upstream" is missing or is missing information, unable to configure GitHub gateway.');
        }

        $this->initializeForHost($repo['host']);
        $this->setRepository($repo['org'], $repo['repo']);
    }

    public function initializeForHost(string $hostname = null): void
    {
        if ($hostname === null) {
            $hostname = self::DEFAULT_HOST;
        }

        if ($this->client === null || $hostname !== $this->hostname) {
            $apiToken = $this->config->getOrFail(['github', $hostname, 'api_token']);
            $this->username = $this->config->getOrFail(['github', $hostname, 'username']);
            $apiUrl = $this->config->get(['github', $hostname, 'api_url'], null);

            $this->clientBuilder = new Builder($this->httpClient);

            $this->client = new GitHubClient($this->clientBuilder, null, $apiUrl);
            $this->client->authenticate($apiToken, null, AuthMethod::ACCESS_TOKEN);
            $this->hostname = $hostname;
        }
    }

    public function createForHost(string $hostname): self
    {
        $github = clone $this;
        $github->initializeForHost($hostname);
        $github->organization = '';
        $github->repository = '';

        // Prevent calling setRepository() from changing the active configuration.
        $github->config = clone $this->config;

        return $github;
    }

    public function setRepository(string $organization, string $repository): void
    {
        $this->organization = $organization;
        $this->repository = $repository;

        $this->config->setActiveRepository($this->hostname, $organization . '/' . $repository);
    }

    public function isAuthenticated(): bool
    {
        if ($this->client === null) {
            return false;
        }

        return \is_array($this->client->currentUser()->show());
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getOrganization(): string
    {
        return $this->organization;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getAuthUsername(): string
    {
        return $this->username;
    }

    public function createRepo(string $organization, string $name, bool $public = true, bool $hasIssues = true)
    {
        \assert($this->client !== null);

        return $this->client->repo()->create(
            $name, // name
            '', // description
            '', // homepage
            $public, // public
            $organization, // organization
            $hasIssues, // has issues
            false, // has wiki
            false, // has downloads
            null, // team-id
            false // auto-init
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getRepoInfo(string $organization, string $name): array
    {
        \assert($this->client !== null);

        return $this->client->repo()->show($organization, $name);
    }

    public function getIssue(int $number)
    {
        \assert($this->client !== null);

        return $this->client->issue()->show(
            $this->organization,
            $this->repository,
            $number
        );
    }

    public function closeIssues(int ...$numbers): void
    {
        \assert($this->client !== null);

        foreach ($numbers as $number) {
            $this->client->issue()->update(
                $this->organization,
                $this->repository,
                $number,
                ['state' => 'closed']
            );
        }
    }

    public function createComment(int $id, string $message)
    {
        \assert($this->client !== null);

        return $this->client->issue()->comments()->create(
            $this->organization,
            $this->repository,
            $id,
            ['body' => $message]
        )['html_url'];
    }

    public function getComments(int $id)
    {
        \assert($this->client !== null);

        return (new ResultPager($this->client))->fetchAll(
            $this->client->issue()->comments(),
            'all',
            [
                $this->organization,
                $this->repository,
                $id,
            ]
        );
    }

    public function getLabels(): array
    {
        \assert($this->client !== null);

        return self::getValuesFromNestedArray(
            $this->client->issue()->labels()->all(
                $this->organization,
                $this->repository
            ),
            'name'
        );
    }

    public function openPullRequest(string $base, string $head, string $subject, string $body)
    {
        \assert($this->client !== null);

        return $this->client->pullRequest()->create(
            $this->organization,
            $this->repository,
            [
                'base' => $base,
                'head' => $head,
                'title' => $subject,
                'body' => $body,
            ]
        );
    }

    public function getPullRequest(int $id, bool $withLabels = false)
    {
        \assert($this->client !== null);

        $api = $this->client->pullRequest();
        $pr = $api->show(
            $this->organization,
            $this->repository,
            $id
        );

        // Still better then BitBucket...
        if ($withLabels && ! isset($pr['labels'])) {
            $api = $this->client->issues();
            $issue = $api->show(
                $this->organization,
                $this->repository,
                $id
            );

            $pr['labels'] = $issue['labels'];
        }

        return $pr;
    }

    public function getCommitStatuses(string $org, string $repo, string $hash): array
    {
        \assert($this->client !== null);

        return (new ResultPager($this->client))->fetchAll($this->client->repo()->statuses(), 'combined', [$org, $repo, $hash]);
    }

    public function getCheckSuitesForReference(string $org, string $repo, string $hash): array
    {
        \assert($this->client !== null);

        return (new ResultPager(
            $this->client
        ))->fetchAll($this->client->repo()->checkSuites(), 'allForReference', [$org, $repo, $hash]);
    }

    public function getCheckRunsForCheckSuite(string $org, string $repo, int $checkSuiteId): array
    {
        \assert($this->client !== null);

        return (new ResultPager($this->client))->fetchAll($this->client->repo()->checkRuns(), 'allForCheckSuite', [$org, $repo, $checkSuiteId]);
    }

    public function getCommits(string $org, string $repo, string $base, string $head): array
    {
        \assert($this->client !== null);

        return (new ResultPager($this->client))->fetchAll(
            $this->client->repo()->commits(),
            'compare',
            [
                $org,
                $repo,
                $base,
                $head,
            ]
        )['commits'];
    }

    public function getPullRequestCommitCount($id): int
    {
        \assert($this->client !== null);

        $graphql = $this->client->graphql();
        $query = <<<'QUERY'
            query($owner: String!, $repo: String!, $prNumber: Int!) {
              repository (owner: $owner, name: $repo) {
                pullRequest (number: $prNumber) {
                  commits {
                    totalCount
                  }
                }
              }
            }
            QUERY;

        $result = $graphql->execute($query, ['owner' => $this->organization, 'repo' => $this->repository, 'prNumber' => $id]);

        if (! isset($result['data'])) {
            throw new \RuntimeException('Unable to determine commit count for pullrequest.');
        }

        return (int) $result['data']['repository']['pullRequest']['commits']['totalCount'];
    }

    public function updatePullRequest($id, array $parameters): void
    {
        \assert($this->client !== null);

        $this->client->pullRequest()->update(
            $this->organization,
            $this->repository,
            $id,
            $parameters
        );
    }

    public function mergePullRequest(int $id, string $title, string $message, string $sha, bool $squash = false)
    {
        \assert($this->client !== null);

        return $this->client->pullRequest()->merge(
            $this->organization,
            $this->repository,
            $id,
            $message,
            $sha,
            $squash,
            $title
        );
    }

    public function createRelease(string $name, string $body, bool $preRelease = false, string $title = null)
    {
        \assert($this->client !== null);

        return $this->client->repo()->releases()->create(
            $this->organization,
            $this->repository,
            [
                'tag_name' => $name,
                'name' => 'Release ' . $name . ($title !== null ? ' ' . $title : ''),
                'body' => $body,
                'prerelease' => $preRelease,
            ]
        );
    }

    public function getDefaultBranch(): string
    {
        \assert($this->client !== null);

        return $this->client->repo()->show($this->getOrganization(), $this->getRepository())['default_branch'];
    }

    private static function getValuesFromNestedArray(array $array, string $key)
    {
        $values = [];

        foreach ($array as $item) {
            $values[] = $item[$key];
        }

        return $values;
    }
}
