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

use Composer\Semver\Comparator;

final class VersionsValidator
{
    /**
     * @param string[] $tags
     *
     * @return Version[]
     */
    public static function getHighestVersions(array $tags): array
    {
        // Sort in descending order (highest first).
        // Trim v prefix as this causes problems with the comparator.
        usort($tags, function ($a, $b) {
            $a = ltrim($a, 'vV');
            $b = ltrim($b, 'vV');

            // No equality check, as this is not possible here.
            return Comparator::lessThan($a, $b) ? 1 : -1;
        });

        $versions = [];
        $currentMajor = -1;

        foreach ($tags as $tag) {
            if (!preg_match('/^v?'.Version::VERSION_REGEX.'/i', $tag, $matches)) {
                continue;
            }

            // Ue the highest version for this major
            // And ignore others until there is another major version.
            if ((int) $matches['major'] !== $currentMajor) {
                $version = Version::fromString($tag);
                $versions[$version->major] = $version;

                $currentMajor = $version->major;
            }
        }

        return $versions;
    }

    /**
     * @param Version[] $versions
     * @param Version   $newVersion
     * @param array     $possibleVersions
     *
     * @return bool
     */
    public static function isVersionContinues(array $versions, Version $newVersion, &$possibleVersions): bool
    {
        if (!count($versions)) {
            return $newVersion->minor !== 0 ||
                   $newVersion->patch !== 0 ||
                   (3 < $newVersion->stability && 1 !== $newVersion->metaver);
        }

        // No points exist for this major.
        if (!isset($versions[$newVersion->major])) {
            // Previous major version doesn't exist or minor/patch are not reset
            if (0 !== $newVersion->minor || 0 !== $newVersion->patch || !isset($versions[$newVersion->major - 1])) {
                return false;
            }

            // Check if "unstable" version starts with meta-version 1
            if ($newVersion->stability < 3 && 1 !== $newVersion->metaver) {
                return false;
            }

            return true;
        }

        $possibleVersions = $versions[$newVersion->major]->getNextVersionCandidates();

        foreach ($possibleVersions as $possibleVersion) {
            if ($possibleVersion->equalTo($newVersion)) {
                return true;
            }
        }

        return false;
    }
}
