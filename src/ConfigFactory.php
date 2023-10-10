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

namespace HubKit;

use HubKit\Service\Git\GitFileReader;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\Exception as ConfigException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Style\StyleInterface;

final class ConfigFactory
{
    private $currentDir;
    private $configFile;
    private ?string $localConfigFile = null;

    public function __construct(
        string $currentDir,
        string $configFile,
        private readonly StyleInterface $style,
        private readonly GitFileReader $gitFileReader,
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

    private static function normalizePath(string $path = null)
    {
        if ($path === null) {
            return;
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            throw new \InvalidArgumentException(
                sprintf('Unable to normalize path "%s", no such file or directory.', $path)
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
        ;

        try {
            return (new Processor())->process($treeBuilder->buildTree(), [$config]);
        } catch (ConfigException $e) {
            throw new \RuntimeException('Configuration contains one or more errors: ' . $e->getMessage(), 1, $e);
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
                        if ($name[0] === '/') {
                            if (@preg_match($name, 'test') === false) {
                                throw new \InvalidArgumentException(sprintf('Invalid regexp %s error: %s.', json_encode($name), json_encode(error_get_last()['message'] ?? 'Unknown')));
                            }

                            if (preg_match('{[\$\^]|/\w+$}', $name) > 0) {
                                throw new \InvalidArgumentException(sprintf('Invalid regexp %s, cannot contain start/end anchor or options. Either "/[5-9]\.x/" not "/^[5-9].x$/i".', json_encode($name)));
                            }
                        } elseif (preg_match('{^(:default|main|master|(#\d+\.x)|(\d+\.([x*]|\d+)))$}', $name) === 0) {
                            throw new \InvalidArgumentException(sprintf('Invalid version or relative pattern %s, must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", "main" or "master", or a regexp like "/0.[1-9]+/".', json_encode($name)));
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
                                ->ifString()
                                ->then(static fn ($v): array => ['url' => $v])
                            ->end()
                            ->beforeNormalization()
                                ->ifTrue(static fn ($v): bool => $v === false)
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
            ->end()
        ;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function resolveLocalConfig(array $config): array
    {
        $treeBuilder = new TreeBuilder('hubkit');
        $treeBuilder->getRootNode()
            ->ignoreExtraKeys(false)
            ->children()
                ->integerNode('schema_version')
                    ->min(2)
                    ->max(2)
                ->end()
                ->append($this->addBranchesNode())
                ->enumNode('adapter')
                    ->values(['github'])
                    ->defaultValue('github')
                ->end()
                ->scalarNode('host')->defaultNull()->end()
                ->scalarNode('repository')->defaultNull()->end()
            ->end()
        ;

        try {
            return (new Processor())->process($treeBuilder->buildTree(), [$config]);
        } catch (ConfigException $e) {
            throw new \RuntimeException('Local configuration contains one or more errors: ' . $e->getMessage(), 1, $e);
        }
    }
}
