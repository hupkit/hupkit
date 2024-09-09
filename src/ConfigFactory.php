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

namespace HubKit;

use HubKit\Service\Git;
use HubKit\Service\Git\GitFileReader;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\Exception as ConfigException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Style\StyleInterface;

final class ConfigFactory
{
    private string $currentDir;
    private string $configFile;
    private ?string $localConfigFile = null;
    private bool $detectedGlobalRepositories = false;

    public function __construct(
        string $currentDir,
        string $configFile,
        private readonly StyleInterface $style,
        private readonly GitFileReader $gitFileReader,
        private readonly Git $git,
    ) {
        $this->currentDir = self::normalizePath($currentDir);
        $this->configFile = self::normalizePath($configFile);

        if (getenv('HUBKIT_NO_LOCAL') !== 'true' && $this->gitFileReader->fileExists('_hubkit', 'config.php')) {
            $this->localConfigFile = $this->gitFileReader->getFile('_hubkit', 'config.php');
        }

        if (getenv('HUBKIT_NO_LOCAL') === 'true') {
            $this->style->warning('Env HUBKIT_NO_LOCAL=true was set, local configuration was not loaded.');
        }
    }

    private static function normalizePath(?string $path = null)
    {
        if ($path === null) {
            return;
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            throw new \InvalidArgumentException(
                \sprintf('Unable to normalize path "%s", no such file or directory.', $path)
            );
        }

        return str_replace('\\', '//', $realPath);
    }

    public function create(): Config
    {
        $config = require $this->configFile;

        if (empty($config['schema_version'])) {
            throw new \RuntimeException('Config "schema_version" is missing in configuration.');
        }

        $config = $this->resolveConfigMainConfig($config);

        if ($config['schema_version'] < 2) {
            $this->style->note('Hubkit "schema_version" 1 in configuration is deprecated and will no longer work in v2.0.');
        } elseif ($this->localConfigFile) {
            try {
                $localeConfig = require $this->localConfigFile;
            } catch (\ParseError $e) {
                throw new \RuntimeException('Unable to load configuration file, run with env `HUBKIT_NO_LOCAL=true` to bypass local config loading. Error: ' . $e->getMessage(), 1, $e);
            }

            $config['_local'] = $this->resolveLocalConfig($localeConfig);
        }

        if (! isset($config['_local']['main_branch'])) {
            // No local configuration provided, but still the main-branch must be resolvable.
            // So store it here, there is no expectation to explicitly get this for a repository.
            //
            // In the future the whole concept for using 'global' configuration for a repository
            // is removed in favor of local-only configuration.
            $config['_main_branch'] = $this->findMainBranch();
        }

        if ($this->detectedGlobalRepositories) {
            $this->style->caution(
                <<<MSG
                    Setting repositories in global configuration is deprecated since HuPKit v1.4 and will be removed in v2.0.
                    Use local repository configurations instead.
                    MSG
            );
        }

        $config['current_dir'] = $this->currentDir;

        return new Config($config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function resolveConfigMainConfig(array $config): array
    {
        $treeBuilder = new TreeBuilder('hubkit');
        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('schema_version')
                    ->min(1)
                    ->max(2)
                ->end()
                ->arrayNode('github')
                    ->useAttributeAsKey('host')
                    ->arrayPrototype()
                        ->normalizeKeys(false)
                        ->children()
                            ->scalarNode('username')->cannotBeEmpty()->end()
                            ->scalarNode('api_token')->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->append($this->addRepositoriesNode())
            ->end()
            ->beforeNormalization()
                ->ifTrue(static fn ($v): bool => isset($v['repos']))
                ->then(function ($v): array {
                    if ($v['schema_version'] > 1) {
                        $this->style->warning('Legacy configuration key "repos" was detected with "schema_version" 2.');
                    }

                    if (isset($v['repositories'])) {
                        $this->style->warning([
                            'Configuration key "repos" and "repositories" are both defined, ignoring legacy "repos" configuration.',
                            'Only use "repositories" with the correct structure as the "repos" key will not be supported in future versions.',
                        ]);

                        return $v;
                    }

                    $repositories = [];

                    foreach ($v['repos'] as $host => $repos) {
                        $repository = [];

                        foreach ($repos as $name => $repo) {
                            $repository[$name]['branches'][':default'] = [];

                            if (isset($repo['sync-tags'])) {
                                $repository[$name]['branches'][':default']['sync-tags'] = $repo['sync-tags'];
                            }

                            if (isset($repo['split'])) {
                                $repository[$name]['branches'][':default']['split'] = $repo['split'];
                            }
                        }

                        $repositories[$host]['repos'] = $repository;
                    }

                    $v['repositories'] = $repositories;
                    unset($v['repos']);

                    return $v;
                })
            ->end()
            ->validate()
                ->ifTrue(static fn ($v): bool => \count($v['repositories']) > 0)
                ->then(function (array $v): array {
                    $this->detectedGlobalRepositories = true;

                    return $v;
                })
            ->end()
        ;

        try {
            return (new Processor())->process($treeBuilder->buildTree(), [$config]);
        } catch (ConfigException $e) {
            throw new \RuntimeException('Configuration contains one or more errors. ' . $e->getMessage(), 1, $e);
        }
    }

    private function addRepositoriesNode(): ArrayNodeDefinition
    {
        return (new TreeBuilder('repositories'))
            ->getRootNode()
            ->normalizeKeys(false)
            ->useAttributeAsKey('host')
            ->arrayPrototype()
                ->normalizeKeys(false)
                ->beforeNormalization()
                    ->ifTrue(static fn ($v): bool => ! isset($v['repos']))
                    ->then(static fn ($v): array => ['repos' => $v])
                ->end()
                ->children()
                    ->arrayNode('repos')
                        ->normalizeKeys(false)
                        ->useAttributeAsKey('repo')
                        ->arrayPrototype()
                            ->children()
                                ->append($this->addBranchesNode())
                                ->append($this->addBranchesAliasNode())
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addBranchesNode(): ArrayNodeDefinition
    {
        return (new TreeBuilder('branches'))
            ->getRootNode()
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->validate()
                ->always()
                ->then(static function ($v): array {
                    foreach ($v as $name => $config) {
                        if ($name === ':default') {
                            continue;
                        }

                        if ($name[0] === '/') {
                            if (@preg_match($name, 'test') === false) {
                                throw new \InvalidArgumentException(\sprintf('Invalid regexp %s error: %s.', json_encode($name, \JSON_UNESCAPED_SLASHES), json_encode(error_get_last()['message'] ?? 'Unknown')), \JSON_UNESCAPED_SLASHES);
                            }

                            if (preg_match('{[\$\^]|/\w+$}', $name) > 0) {
                                throw new \InvalidArgumentException(\sprintf('Invalid regexp %s, cannot contain start/end anchor or options. Either "/[5-9]\.x/" not "/^[5-9].x$/i".', json_encode($name)));
                            }
                        } else {
                            if ($name[0] === '#') {
                                $name = mb_substr($name, 1);
                            }

                            // 1.* would be invalid as branch-name
                            if (preg_match('{^(\d+\.\*)$}', $name) === 1) {
                                continue;
                            }

                            try {
                                self::validateBranchName($name);
                            } catch (\InvalidArgumentException $e) {
                                throw new \InvalidArgumentException(\sprintf('Invalid branch-name or relative pattern %s, must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: %s', json_encode($name, \JSON_UNESCAPED_SLASHES), $e->getMessage()), 0, $e);
                            }
                        }
                    }

                    return $v;
                })
            ->end()
            ->arrayPrototype()
                ->normalizeKeys(false)
                ->treatFalseLike(['maintained' => false, 'upmerge' => false, 'sync-tags' => true, 'ignore-default' => true, 'split' => []])
                ->children()
                    ->booleanNode('upmerge')->defaultTrue()->info('Set to false to disable upmerge for this branch configuration, and continue with next possible version')->end()
                    ->booleanNode('sync-tags')->defaultTrue()->end()
                    ->booleanNode('maintained')->defaultTrue()->end()
                    ->booleanNode('ignore-default')->defaultFalse()->info('Ignore the ":default" branch configuration')->end()
                    ->arrayNode('split')
                        ->normalizeKeys(false)
                        ->useAttributeAsKey('directory')
                        ->arrayPrototype()
                            ->normalizeKeys(false)
                            ->beforeNormalization()
                                ->ifTrue(static fn ($v): bool => \is_string($v) || $v === false)
                                ->then(static fn ($v): array => ['url' => $v])
                            ->end()
                            ->children()
                                ->scalarNode('sync-tags')
                                    ->defaultNull()
                                    ->treatNullLike(null)
                                    ->validate()
                                        ->ifTrue(static fn ($v): bool => $v !== null && ! \is_bool($v))
                                        ->thenInvalid('Value %s must be either true, false or null.')
                                    ->end()
                                ->end()
                                ->scalarNode('url')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                // When marked as unmaintained 'unset' all values
                ->validate()
                    ->ifTrue(static fn (array $v): bool => $v['maintained'] === false)
                    ->then(static fn (): array => ['maintained' => false, 'upmerge' => false, 'sync-tags' => false, 'ignore-default' => true, 'split' => []])
                ->end()
                ->validate()
                    ->always()
                    ->then(static function ($v): array {
                        $found = [];

                        foreach ($v['split'] as $prefix => $config) {
                            if (preg_match('{^\s|\s$}', $prefix)) {
                                throw new \InvalidArgumentException(\sprintf('Invalid prefix %s. Cannot start or end with space characters.', json_encode($prefix, \JSON_UNESCAPED_SLASHES)));
                            }

                            if (preg_match('{[\n\r]}', $prefix)) {
                                throw new \InvalidArgumentException(\sprintf('Invalid prefix %s. Cannot contain newline characters.', json_encode($prefix, \JSON_UNESCAPED_SLASHES)));
                            }

                            if ($prefix === '') {
                                throw new \InvalidArgumentException('A prefix cannot be empty.');
                            }

                            if ($prefix[0] === '/' || str_ends_with($prefix, '/')) {
                                throw new \InvalidArgumentException(\sprintf('Invalid prefix %s. Cannot start or end with a slash.', json_encode($prefix, \JSON_UNESCAPED_SLASHES)));
                            }

                            if (str_contains($prefix, '/../')) {
                                throw new \InvalidArgumentException(\sprintf('Invalid prefix %s. Must be a path relative to the root directory, without backtracking.', json_encode($prefix, \JSON_UNESCAPED_SLASHES)));
                            }

                            if (isset($found[mb_strtolower($prefix)])) {
                                throw new \InvalidArgumentException(\sprintf('Prefix %s is already set as %s.', json_encode($prefix, \JSON_UNESCAPED_SLASHES), json_encode($found[mb_strtolower($prefix)], \JSON_UNESCAPED_SLASHES)));
                            }

                            $found[mb_strtolower($prefix)] = $prefix;
                        }

                        return $v;
                    })
                ->end()
            ->end()
        ;
    }

    private function addBranchesAliasNode(): ArrayNodeDefinition
    {
        return (new TreeBuilder('branches_alias'))
            ->getRootNode()
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->validate()
                ->always()
                ->then(static function ($v): array {
                    foreach ($v as $name => $label) {
                        try {
                            self::validateBranchName($name);
                        } catch (\InvalidArgumentException $e) {
                            throw new \InvalidArgumentException(\sprintf('Invalid branch-name %s: %s', json_encode($name), $e->getMessage()), 0, $e);
                        }

                        if (! \is_string($label)) {
                            throw new \InvalidArgumentException(\sprintf('Invalid branch-alias for %s, should should be a string to prevent casting mismatches.', $name));
                        }

                        if (! preg_match('/^([1-9]\d*\.\d+)$/', $label)) {
                            throw new \InvalidArgumentException(\sprintf('Invalid branch-alias for %s, should consists of major and minor version without any prefix or suffix. like: 1.2. Got: %s', $name, $label));
                        }

                        $v[$name] = $label . '-dev';
                    }

                    return $v;
                })
            ->end()
            ->scalarPrototype()
            ->end()
        ;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function resolveLocalConfig(array $config): array
    {
        $treeBuilder = new TreeBuilder('hubkit');
        $treeBuilder->getRootNode()
            ->ignoreExtraKeys(false)
            ->children()
                ->integerNode('schema_version')
                    ->isRequired()
                    ->min(2)
                    ->max(2)
                ->end()
                ->append($this->addBranchesNode())
                ->append($this->addBranchesAliasNode())
                ->enumNode('adapter')
                    ->values(['github'])
                    ->defaultValue('github')
                ->end()
                ->scalarNode('host')->defaultNull()->end()
                ->scalarNode('repository')->defaultNull()->end()
                ->scalarNode('main_branch')
                    ->defaultNull()
                    ->validate()
                        ->always(fn ($v) => $v === null ? $this->findMainBranch() : self::validateBranchName((string) $v))
                    ->end()
                ->end()
                ->arrayNode('pull_request')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('split')
                            ->values(['all', 'changed-only', 'none'])
                            ->defaultValue('all')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        try {
            return (new Processor())->process($treeBuilder->buildTree(), [$config]);
        } catch (ConfigException $e) {
            throw new \RuntimeException('Local configuration contains one or more errors. ' . $e->getMessage(), 1, $e);
        }
    }

    private function findMainBranch(): string
    {
        if ($this->git->branchExists('main')) {
            $branch = 'main';
        } elseif ($this->git->branchExists('master')) {
            $branch = 'master';
        } else {
            $versions = $this->git->getVersionBranches();
            /** @var string|null $branch */
            $branch = array_pop($versions);

            if ($branch === null) {
                try {
                    $branch = $this->git->getActiveBranchName();
                } catch (\Throwable $e) {
                    $this->style->error([
                        'Could not detect "main_branch", neither "main", "master" or any versioned branch exist, defaulting to "main".',
                        $e->getMessage(),
                    ]);

                    return 'main';
                }
            }
        }

        $this->style->block([
            'No "main_branch" was not set, this value will default to "main" in HuPKit v2.0.' . "\n" .
            \sprintf('The "main_branch" is resolved as "%s", set the "main_branch" option in your local configuration to change this.', $branch),
        ], null, 'fg=yellow', ' ! ');

        return $branch;
    }

    private static function validateBranchName(string $v): string
    {
        if (mb_strtoupper($v) === 'HEAD') {
            throw new \InvalidArgumentException('Cannot use Git ref HEAD as branch name.');
        }

        if (preg_match('{^(heads|tags|remotes|notes)/}i', $v) === 1) {
            throw new \InvalidArgumentException('Cannot start with Git refs (heads, tags, remotes, notes)/.');
        }

        if (preg_match('{^([\p{L}\p{N}]+([./_-]?[\p{L}\p{N}]+)?)+$}ui', $v) !== 1) {
            throw new \InvalidArgumentException('Invalid name provided, must follow the Git convention for branch names.');
        }

        return $v;
    }
}
