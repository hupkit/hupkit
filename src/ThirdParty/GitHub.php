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

namespace HubKit\ThirdParty;

use Github\Client as GitHubClient;
use Github\ResultPager;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleClientAdapter;
use HubKit\Service\Git;

final class GitHub
{
    private $client;

    private $username;
    private $apiToken;

    private $organization;
    private $repository;

    public function __construct(Client $client, string $username, string $apiToken)
    {
        $this->client = new GitHubClient(new GuzzleClientAdapter());
        $this->client->authenticate($apiToken, null, GitHubClient::AUTH_HTTP_TOKEN);

        $this->username = $username;
        $this->apiToken = $apiToken;
    }

    public function autoConfigure(Git $git)
    {
        $repo = $git->getRemoteInfo('remote.upstream.url');

        if ('' === $repo['org']) {
            throw new \RuntimeException('Remote "upstream" is missing, unable to configure GitHub gateway.');
        }

        if ('github.com' !== $repo['host']) {
            throw new \RuntimeException('Remote "upstream" does not point to a GitHub repository.');
        }

        $this->setRepository($repo['org'], $repo['repo']);
    }

    public function setRepository(string $organization, string $repository)
    {
        $this->organization = $organization;
        $this->repository = $repository;
    }

    public function isAuthenticated(): bool
    {
        return is_array($this->client->api('me')->show());
    }

    public function createRepo(string $organization, string $name, bool $public = true, bool $hasIssues = true)
    {
        $repo = $this->client->api('repo');

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

    public function updateRepo(string $organization, string $name, array $values)
    {
        $repo = $this->client->api('repo');

        return $repo->update(
            $organization,
            $name, // name
            $values
        );
    }

    public function getIssues(array $parameters = [], int $limit = null)
    {
        $pager = new ResultPager($this->client);
        $api = $this->client->api('issue');
        $perPage = $api->getPerPage();

        if (!$limit || $limit > 100) {
            return $pager->fetchAll(
                $api,
                'all',
                [
                    $this->organization,
                    $this->repository,
                    $parameters,
                ]
            );
        }

        try {
            $api->setPerPage($limit);

            return $pager->fetch(
                $this->client->api('issue'),
                'all',
                [
                    $this->organization,
                    $this->repository,
                    $parameters,
                ]
            );
        } finally {
            $api->setPerPage($perPage);
        }
    }

    public function updateIssue(int $id, array $parameters)
    {
        $api = $this->client->api('issue');

        $api->update(
            $this->organization,
            $this->repository,
            $id,
            $parameters
        );
    }

    public function createComment(int $id, string $message)
    {
        $api = $this->client->api('issue')->comments();

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
            $this->client->api('issue')->comments(),
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
        $api = $this->client->api('issue')->labels();

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
        $api = $this->client->api('pull_request');

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

    public function getPullRequest(int $id)
    {
        $api = $this->client->api('pull_request');

        return $api->show(
            $this->organization,
            $this->repository,
            $id
        );
    }

    public function getPullRequestUrl(int $id)
    {
        return sprintf('%s/%s/%s/pull/%d', $this->domain, $this->organization, $this->repository, $id);
    }

    public function getCommitStatuses($org, $repo, $hash)
    {
        $pager = new ResultPager($this->client);

        return $pager->fetchAll($this->client->api('repo')->statuses(), 'combined', [$org, $repo, $hash])['statuses'];
    }

    /**
     * {@inheritdoc}
     */
    public function updatePullRequest($id, array $parameters)
    {
        $api = $this->client->api('pull_request');

        $api->update(
            $this->organization,
            $this->repository,
            $id,
            $parameters
        );
    }

    public function createRelease(string $name, string $body, $preRelease = false)
    {
        $api = $this->client->api('repo')->releases();

        return $api->create(
            $this->organization,
            $this->repository,
            [
                'tag_name' => $name,
                'name' => 'Release '.$name,
                'body' => $body,
                'draft' => true,
                'prerelease' => $preRelease,
            ]
        );
    }

    public function publishRelease(string $name, int $id = null)
    {
        $api = $this->client->api('repo')->releases();

        if (null === $id) {
            foreach ($api->all() as $release) {
                if ($name === $release['tag_name']) {
                    $id = $release['id'];
                }
            }
        }

        return $api->update(
            $this->organization,
            $this->repository,
            [
                'id' => $id,
                'draft' => false,
            ]
        );
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
