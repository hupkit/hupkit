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

use Composer\Semver\Comparator;
use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\StringUtil;
use HubKit\ThirdParty\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class ReleaseHandler extends GitBaseHandler
{
    /**
     * Match most common version formats.
     *
     * * No prefix or build-meta (matched)
     * * For historic reasons stability versions may have a hyphen or dot
     *   and is considered optional
     */
    const VERSION_REGEX = '(?P<major>\d++)\.(?P<minor>\d++)(?:\.(?P<patch>\d++))?(?:[-.]?(?P<stability>beta|RC|alpha|stable)(?:[.-]?(?P<metaver>\d+))?)?';

    /**
     * Stability indexes, higher means more stable.
     *
     * @var string[]
     */
    private static $stabilises = ['alpha' => 0, 'beta' => 1, 'rc' => 2, 'stable' => 3];

    private $process;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github, CliProcess $process)
    {
        parent::__construct($style, $git, $github);
        $this->process = $process;
    }

    public function handle(Args $args, IO $io)
    {
        $this->informationHeader();

        $io->writeLine($this->validateVersion($args->getArgument('version')));
    }

    private function validateVersion(string $version): string
    {
        if (!preg_match('/^'.self::VERSION_REGEX.'$/', $version, $matches)) {
            throw new \InvalidArgumentException(
                'Invalid version format, expects an SemVer compatible version without prefix or build-meta. '.
                'Eg. "1.0.0", "1.0", "1.0" or "1.0.0-beta1", "1.0.0-beta-1"'
            );
        }

        $matches['patch'] = $matches['patch'] ?? 0;
        $matches['stability'] = $matches['stability'] ?? 'stable';
        $matches['metaver'] = $matches['metaver'] ?? '0';

        foreach (['major', 'minor', 'patch', 'metaver'] as $v) {
            if (strlen($matches[$v]) > 1 && '0' === $matches[$v][0]) {
                throw new \InvalidArgumentException(
                    sprintf('Version segment "%s" is expected to an integer, got "%s".', $v, $matches[$v])
                );
            }
        }

        $versions = $this->getHighestVersions();

        if (!$this->isVersionContinues($versions, $matches)) {
            $this->style->warning(
                [
                    'It appears there is gap compared to the last version.',
                    'Please check your input or confirm is this intended.',
                ]
            );
        }

        if ('stable' !== strtolower($matches['stability'])) {
            return sprintf(
                '%d.%d.%d-%s%d',
                (int) $matches['major'],
                (int) $matches['minor'],
                (int) $matches['patch'],
                strtoupper($matches['stability']),
                (int) $matches['mataver']
            );
        }

        return sprintf('%d.%d.%d', (int) $matches['major'], (int) $matches['minor'], (int) $matches['patch']);
    }

    private function getHighestVersions(): array
    {
        $tags = StringUtil::splitLines($this->process->mustRun('git tag --list')->getOutput());
        usort($tags, function ($a, $b) {
            if (Comparator::equalTo($a, $b)) {
                return 0;
            }

            return Comparator::lessThan($a, $b) ? -1 : 1;
        });

        $versions = array_map(
            function (string $v) {
                // Don't match end so it's possible to detect build-metadata.
                if (preg_match('/^v?'.self::VERSION_REGEX.'/', $v, $matches)) {
                    return [
                        //'full' => $v,
                        'major' => (int) $matches['major'],
                        'minor' => (int) $matches['minor'],
                        'patch' => (int) $matches['patch'],
                        'stability' => self::$stabilises[strtolower(($matches['stability'] ?? 'stable'))],
                        'metaver' => (int) ($matches['metaver'] ?? 0),
                    ];
                }

                return false;
            },
            $tags
        );

        // Keeps a list of all highest version.
        // As: major => [minor => x, patch => x, stability => vvv, metaver => x]
        // Any version higher then whats are already stored gets used.
        $resolvedVersions = [];

        foreach ($versions as $version) {
            // None supported version detected.
            if (!$version) {
                continue;
            }

            // As versions are sorted we can simple use the major as key.
            // Any newer version will simple overwrite the old one!
            $resolvedVersions[$version['major']] = $version;
        }

        return $resolvedVersions;
    }

    private function isVersionContinues(array $versions, array $newVersion): bool
    {
        if (!count($versions)) {
            return true;
        }

        $newVersion = [
            'major' => (int) $newVersion['major'],
            'minor' => (int) $newVersion['minor'],
            'patch' => (int) $newVersion['patch'],
            'stability' => self::$stabilises[strtolower($newVersion['stability'])],
            'metaver' => (int) $newVersion['metaver'],
        ];

        // No points exist for this major.
        if (!isset($versions[$newVersion['major']])) {
            // Previous major version doesn't exist or minor/patch are not reset
            if (0 !== $newVersion['minor'] ||
                0 !== $newVersion['patch'] ||
                !isset($versions[$newVersion['major'] - 1])
            ) {
                return false;
            }

            // Check if "unstable" version starts with meta-version 1
            if ($newVersion['stability'] < 3 && 1 !== $newVersion['metaver']) {
                return false;
            }

            return true;
        }

        $current = $versions[$newVersion['major']];

        foreach (['minor', 'patch', 'stability', 'metaver'] as $k) {
            // Current version is newer
            if ($current[$k] > $newVersion[$k]) {
                return false;
            }

            // New is higher, but is the increment correct?
            if ($newVersion[$k] > $current[$k]) {
                if ('stability' === $k) {
                    break; // Stability may jump higher
                }

                if ($newVersion[$k] - 1 !== $current[$k]) {
                    return false;
                }
            }
        }

        // All seems good, but better check to be sure.
        // The loop above doesn't check whether patch or metaver is reset after a minor increase.

        // An increase in minor point must reset patch.
        if (0 !== $newVersion['patch'] && $newVersion['minor'] > $current['minor']) {
            return false;
        }

        // An increase in stability point must reset metaver (unless stable).
        if (1 !== $newVersion['patch'] && 3 < $newVersion['stability'] && $newVersion['stability'] > $current['stability']) {
            return false;
        }

        return true;
    }
}
