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

            // Use the highest version for this major
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
     * @param Version   $new
     * @param array     $possibleVersions
     *
     * @return bool
     */
    public static function isVersionContinues(array $versions, Version $new, &$possibleVersions): bool
    {
        if (!count($versions)) {
            $possibleVersions = [
                Version::fromString('0.1.0'),
                Version::fromString('1.0.0-ALPHA1'),
                Version::fromString('1.0.0-BETA1'),
                Version::fromString('1.0.0'),
            ];
        } elseif (isset($versions[$new->major])) {
            $possibleVersions = $versions[$new->major]->getNextVersionCandidates();
        } else {
            // No versions for this major, so look-back till we find an existing major version
            $expectedMajor = $new->major;

            while (!isset($versions[$expectedMajor]) && $expectedMajor > 0) {
                --$expectedMajor;
            }

            // Actually need the version *after* the existing one.
            ++$expectedMajor;

            $possibleVersions = [
                Version::fromString($expectedMajor.'.0.0-ALPHA1'),
                Version::fromString($expectedMajor.'.0.0-BETA1'),
                Version::fromString($expectedMajor.'.0.0'),
            ];
        }

        foreach ($possibleVersions as $possibleVersion) {
            if ($possibleVersion->equalTo($new)) {
                return true;
            }
        }

        return false;
    }
}
