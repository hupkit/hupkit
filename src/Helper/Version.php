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

final class Version
{
    /**
     * Match most common version formats.
     *
     * * No prefix or build-meta (matched)
     * * For historic reasons stability versions may have a hyphen or dot
     *   and is considered optional
     */
    const VERSION_REGEX = '(?P<major>\d++)\.(?P<minor>\d++)(?:\.(?P<patch>\d++))?(?:[-.]?(?P<stability>beta|RC|alpha|stable)(?:[.-]?(?P<metaver>\d+))?)?';

    public $major;
    public $minor;
    public $patch;
    public $stability;
    public $metaver;
    public $full;

    /**
     * Stability indexes, higher means more stable.
     *
     * @var int[]
     */
    private static $stabilises = ['alpha' => 0, 'beta' => 1, 'rc' => 2, 'stable' => 3];

    private function __construct(int $major, int $minor, int $patch, int $stability, int $metaver = 0)
    {
        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
        $this->stability = $stability;
        $this->metaver = $metaver;

        if ($stability < 3) {
            $this->full = sprintf(
                '%d.%d.%d-%s%d',
                $this->major,
                $this->minor,
                $this->patch,
                strtoupper(array_search($this->stability, self::$stabilises, true)),
                $this->metaver
            );
        } elseif ($this->metaver > 0) {
            throw new \InvalidArgumentException('Meta version of the stability flag cannot be set for stable.');
        } else {
            $this->full = sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);
        }
    }

    public function __toString()
    {
        return $this->full;
    }

    public static function fromString(string $version): Version
    {
        if (preg_match('/^v?'.self::VERSION_REGEX.'$/i', $version, $matches)) {
            return new self(
                (int) $matches['major'],
                (int) $matches['minor'],
                (int) ($matches['patch'] ?? 0),
                self::$stabilises[strtolower($matches['stability'] ?? 'stable')],
                (int) ($matches['metaver'] ?? 0)
            );
        }

        // Check for 0.x-stable (really?? who does this...)

        throw new \InvalidArgumentException(
            sprintf(
                'Unable to parse version "%s" Expects an SemVer compatible version without build-metadata. '.
                'Eg. "1.0.0", "1.0", "1.0" or "1.0.0-beta1", "1.0.0-beta-1"',
                $version
            )
        );
    }

    /**
     * Returns a list of possible feature versions.
     *
     * * 1.0.0 -> [1.0.1, 1.1.0, 2.0.0-beta1, 2.0.0]
     * * 1.0.1 -> [1.0.2, 1.2.0, 2.0.0-beta1, 2.0.0]
     * * 1.1.0 -> [1.2.0, 1.2.0-beta1, 2.0.0-beta1, 2.0.0]
     * * 1.0.0-beta1 -> [1.0.0-beta2, 1.0.0] (no minor or major increases)
     * * 1.0.0-alpha1 -> [1.0.0-alpha2, 1.0.0-beta1, 1.0.0] (no minor or major increases)
     *
     * Note: The alpha stability-version increases are only considered for alpha releases,
     * not others. 1.0.0 will never consider 2.0.0-alpha1, as alpha is reversed for pre
     * 1.0.0-stable releases.
     *
     * @return Version[]
     */
    public function getNewVersionCandidates(): array
    {
        $candidates = [];

        // (int $major, int $minor, int $patch, int $stability, int $metaver)

        // New major release
        $candidates[] = new Version($this->major + 1, 0, 0, 3);

        // Alpha release, so pre 1.0.0-stable but after 0.x
        

        // Major is already listed, a new major leap 0.x -> 2.0 is prohibited.
        if ($this->stability === 0) {
            $candidates[] = new Version($this->major, $this->minor, 0, 0, $this->metaver + 1); // next alpha
            $candidates[] = new Version($this->major, $this->minor, 0, 1, 1); // beta
            $candidates[] = new Version($this->major, $this->minor, 0, 2, 2); // rc

            // No future candidates considered.
            return $candidates;
        }

        // beta release
        if ($this->stability === 1) {
            $candidates[] = new Version($this->major, 0, 0, 1, $this->metaver + 1); // next beta
            $candidates[] = new Version($this->major, 0, 0, 1, 2); // rc
        }


        return $candidates;
    }

    public function equalTo(Version $second): bool
    {
        return $this->full === $second->full;
    }
}
