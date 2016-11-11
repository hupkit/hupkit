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
        // A 0 major release is always
        if (0 === $major) {
            $stability = 0;
            $metaver = 1;
        }

        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
        $this->stability = $stability;
        $this->metaver = $metaver;

        if (3 === $stability && $this->metaver > 0) {
            throw new \InvalidArgumentException('Meta version of the stability flag cannot be set for stable.');
        }

        if ($major > 0 && $stability < 3) {
            $this->full = sprintf(
                '%d.%d.%d-%s%d',
                $this->major,
                $this->minor,
                $this->patch,
                strtoupper(array_search($this->stability, self::$stabilises, true)),
                $this->metaver
            );
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
     * * 0.1.0 -> [0.1.1, 0.2.0, 1.0.0-beta1, 1.0.0]
     * * 1.0.0 -> [1.0.1, 1.1.0, 2.0.0-beta1, 2.0.0]
     * * 1.0.1 -> [1.0.2, 1.2.0, 2.0.0-beta1, 2.0.0]
     * * 1.1.0 -> [1.2.0, 1.2.0-beta1, 2.0.0-beta1, 2.0.0]
     * * 1.0.0-beta1 -> [1.0.0-beta2, 1.0.0] (no minor or major increases)
     * * 1.0.0-alpha1 -> [1.0.0-alpha2, 1.0.0-beta1, 1.0.0] (no minor or major increases)
     *
     * @return Version[]
     */
    public function getNextVersionCandidates(): array
    {
        $candidates = [];

        // Pre first-stable, so 0.x-[rc,beta,stable] releases are not considered.
        // Use alpha as stability with metaver 1, 0.2-alpha2 is simple ignored.
        // If anyone really uses this... not our problem :)
        if (0 === $this->major) {
            $candidates[] = new self(0, $this->minor, $this->patch + 1, 0, 1); // patch increase
            $candidates[] = new self(0, $this->minor + 1, 0, 0, 1); // minor increase
            $candidates[] = new self(1, 0, 0, 1, 1); // 1.0.0-BETA1

            // stable (RC usually follows *after* beta, but jumps to stable are accepted)
            // RC is technically valid, but not very common and therefor ignored.
            $candidates[] = new self(1, 0, 0, 3);

            // No future candidates considered.
            return $candidates;
        }

        // Latest is unstable, may increase stability or metaver (nothing else)
        // 1.0.1-beta1 is not accepted, an (un)stability only applies for x.0.0
        if ($this->stability < 3) {
            $candidates[] = new self($this->major, $this->minor, 0, $this->stability, $this->metaver + 1);

            for ($s = $this->stability + 1; $s < 3; ++$s) {
                $candidates[] = new self($this->major, $this->minor, 0, $s, 1);
            }

            $candidates[] = new self($this->major, $this->minor, 0, 3);

            return $candidates;
        }

        // Stable, so a patch, major or new minor (with lower stability) version is possible
        // RC is excluded.
        $candidates[] = new self($this->major, $this->minor, $this->patch + 1, 3);
        $candidates[] = new self($this->major, $this->minor + 1, 0, 1, 1); // BETA1 for next minor
        $candidates[] = new self($this->major, $this->minor + 1, 0, 3); // stable next minor

        // New (un)stable major (excluding RC)
        $candidates[] = new self($this->major + 1, 0, 0, 0, 1); // alpha
        $candidates[] = new self($this->major + 1, 0, 0, 1, 1); // beta
        $candidates[] = new self($this->major + 1, 0, 0, 3); // stable

        return $candidates;
    }

    public function equalTo(Version $second): bool
    {
        return $this->full === $second->full;
    }
}
