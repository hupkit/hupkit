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

final class ReleaseHandler extends GitBaseHandler
{
    /**
     * Match most common version formats.
     *
     * * No prefix or build-meta (matched)
     * * For historic reasons stability versions may have a hyphen or dot
     *   and is considered optional
     */
    const VERSION_REGEX = '(?P<major>\d++)\.(?P<minor>\d++)(?:\.(?P<patch>\d++))?(?:[-.]?(?P<stability>beta|RC|alpha)(?:-?(?P<metaver>\d+))?)?';

    private $process;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github, CliProcess $process)
    {
        parent::__construct($style, $git, $github);
        $this->process = $process;
    }

    public function handle(Args $args, IO $io)
    {
        $this->informationHeader();
    }

    private function validateVersion(string $version): string
    {
        if (!preg_match('/^'.self::VERSION_REGEX.'$/', $version, $matches)) {
            throw new \InvalidArgumentException(
                'Invalid version format, expects an SemVer compatible version without prefix. '.
                'Eg. "1.0.0", "1.0", "1.0" or "1.0.0-beta1", "1.0.0-beta-1"'
            );
        }

        if ('' === $matches['patch']) {
            $matches['patch'] = 0;
        }

        foreach (['major', 'minor', 'patch', 'metaver'] as $v) {
            if (strlen($matches[$v]) > 1 && '0' === $matches[$v][0]) {
                throw new \InvalidArgumentException(
                    sprintf('Version segment "%s" is expected to an integer, got "%s".', $v, $matches[$v])
                );
            }
        }

        $this->tagsToHighestVersion();
        // XXX check for gaps
    }

    private function tagsToHighestVersion():void
    {
        $tags = array_map(
            array_filter(
                StringUtil::splitLines($this->process->mustRun('git tag --list')->getOutput()),
                function (string $v) {
                    // Don't match end so it's possible to detect build-metadata.
                    if (preg_match('/^v?'.self::VERSION_REGEX.'/', $v, $matches)) {
                        return [
                            'full' => $v,
                            'major' => (int) $matches['major'],
                            'minor' => (int) $matches['minor'],
                            'patch' => (int) $matches['patch'],
                            'stability' => $matches['stability'],
                            'metaver' => (int) $matches['metaver'],
                        ];
                    }

                    return false;
                }
            )
        );

        // Keeps a list of all highest version.
        // As: major => [minor => x, patch => x, stability => vvv, metaver => x]
        // Any version higher then whats are already stored gets used.
        $versions = [];

        foreach ($tags as $tag) {
            if (!$tag) {
                continue;
            }

            if (!isset($versions[$tag['major']])) {
                $versions[$tag['major']] = $tag;

                continue;
            }

            if ($tag['minor'] > $versions[$tag['major']]['minor']) {
                $versions[$tag['major']] = $tag;

                continue;
            }

            // Minor version is lower so ignore to simplify the process
            if ($tag['minor'] < $versions[$tag['major']]['minor']) {
                continue;
            }

            // Minor can only be equal now
            if ($tag['patch'] > $versions[$tag['major']]['patch']) {
                $versions[$tag['major']] = $tag;

                continue;
            }

            // OK, major, minor are equal and patch is not higher.
            // So now we need to check the stability.
            // alpha < beta < RC.

            if ('' === $tag['stability']) {
                continue;
            }

        }


    }
}
