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

namespace HubKit\Service;

use Github\Client as GitHubClient;
use Github\ResultPager;
use Http\Client\HttpClient;
use HubKit\Config;

class GitHub
{
    const DEFAULT_HOST = 'github.com';

    private $httpClient;
    private $config;
    /** @var GitHubClient */
    private $client;
    private $organization;
    private $repository;
    private $hostname;
    private $username;

    public function __construct(HttpClient $client, Config $config)
    {
        $this->httpClient = $client;
        $this->config = $config;
    }

    public function autoConfigure(Git $git)
    {
        $repo = $git->getRemoteInfo('upstream');

        if ('' === $repo['org']) {
            throw new \RuntimeException('Remote "upstream" is missing, unable to configure GitHub gateway.');
        }

        $this->initializeForHost($repo['host']);
        $this->setRepository($repo['org'], $repo['repo']);
    }

    public function initializeForHost(string $hostname = null)
    {
        if (null === $hostname) {
            $hostname = self::DEFAULT_HOST;
        }

        if (null === $this->client || $hostname !== $this->hostname) {
            $apiToken = $this->config->getOrFail(['github', $hostname, 'api_token']);
            $this->username = $this->config->getOrFail(['github', $hostname, 'username']);
            $apiUrl = $this->config->get(['github', $hostname, 'api_url'], null);

            $this->client = new GitHubClient($this->httpClient, null, $apiUrl);
            $this->client->authenticate($apiToken, null, GitHubClient::AUTH_HTTP_TOKEN);
            $this->hostname = $hostname;
        }
    }

    public function setRepository(string $organization, string $repository)
    {
        $this->organization = $organization;
        $this->repository = $repository;
    }

    public function isAuthenticated()
    {
        return is_array($this->client->currentUser()->show());
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
        $repo = $this->client->repo();

        return $repo->create(
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

    public function getIssue(int $number)
    {
        return $this->client->issue()->show(
            $this->organization,
            $this->repository,
            $number
        );
    }

    public function createComment(int $id, string $message)
    {
        $api = $this->client->issue()->comments();

        $comment = $api->create(
            $this->organization,
            $this->repository,
            $id,
            ['body' => $message]
        );

        return $comment['html_url'];
    }

    public function getComments(int $id)
    {
        $pager = new ResultPager($this->client);

        return $pager->fetchAll(
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
        $api = $this->client->issue()->labels();

        return self::getValuesFromNestedArray(
            $api->all(
                $this->organization,
                $this->repository
            ),
            'name'
        );
    }

    public function openPullRequest(string $base, string $head, string $subject, string $body)
    {
        $api = $this->client->pullRequest();

        return $api->create(
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
        $api = $this->client->pullRequest();
        $pr = $api->show(
            $this->organization,
            $this->repository,
            $id
        );

        // Still better then BitBucket...
        if ($withLabels && !isset($pr['labels'])) {
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
        $pager = new ResultPager($this->client);

        return $pager->fetchAll($this->client->repo()->statuses(), 'combined', [$org, $repo, $hash]);
    }

    public function getCommits(string $org, string $repo, string $base, string $head): array
    {
        $pager = new ResultPager($this->client);

        return $pager->fetchAll(
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

    public function updatePullRequest($id, array $parameters)
    {
        $api = $this->client->pullRequest();

        $api->update(
            $this->organization,
            $this->repository,
            $id,
            $parameters
        );
    }

    public function mergePullRequest(int $id, string $title, string $message, string $sha, bool $squash = false)
    {
        $this->setApVersion('polaris-preview');
        $api = $this->client->pullRequest();

        return $api->merge(
            $this->organization,
            $this->repository,
            $id,
            $message,
            $sha,
            $squash,
            $title
        );
    }

    public function createRelease(string $name, string $body, $preRelease = false)
    {
        $api = $this->client->repo()->releases();

        return $api->create(
            $this->organization,
            $this->repository,
            [
                'tag_name' => $name,
                'name' => 'Release '.$name,
                'body' => $body,
                'prerelease' => $preRelease,
            ]
        );
    }

    private function setApVersion(string $version)
    {
        $this->client->addHeaders(['Accept' => sprintf('application/vnd.github.%s+json', $version)]);
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
