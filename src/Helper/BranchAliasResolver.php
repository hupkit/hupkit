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

use HubKit\Service\Git;
use Symfony\Component\Console\Style\StyleInterface;

class BranchAliasResolver
{
    private readonly string $cwd;
    private string $detectedBy = '';

    public function __construct(
        private readonly StyleInterface $style,
        private readonly Git $git,
        string $cwd = null
    ) {
        $this->cwd = $cwd ?? getcwd();
    }

    public function getAlias(): string
    {
        $branch = $this->git->getPrimaryBranch();
        $alias = $this->getAliasByComposer($branch);

        if ($alias !== '') {
            $this->detectedBy = 'composer.json "extra.branch-alias.dev-' . $branch . '"';

            return $alias;
        }

        $this->detectedBy = 'Git config "branch.' . $branch . '.alias"';
        $alias = $this->git->getGitConfig('branch.' . $branch . '.alias');

        if ($alias !== '') {
            return $alias;
        }

        return $this->askNewAlias($branch);
    }

    public function getDetectedBy(): string
    {
        return $this->detectedBy;
    }

    private function getAliasByComposer(string $branch): string
    {
        if (! file_exists($this->cwd . '/composer.json')) {
            return '';
        }

        $composer = json_decode(file_get_contents($this->cwd . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        if (! isset($composer['extra']['branch-alias']['dev-' . $branch])) {
            return '';
        }

        $label = $composer['extra']['branch-alias']['dev-' . $branch];

        // Unstable releases are known to change often so use `1.0-dev` as final destination
        if ($label[0] === '0') {
            $label = '1.0-dev';
        }

        return $label;
    }

    private function askNewAlias(string $branch): string
    {
        $this->style->note(
            [
                'No branch-alias found for "' . $branch . '", please provide an alias.',
                'This should be the version "' . $branch . '" will become.',
                'If the last release is 2.1 the next will be eg. 2.2 or 3.0.',
            ]
        );

        $label = (string) $this->style->ask(
            'Branch alias',
            null,
            static function ($value) {
                if (! preg_match('/^\d+\.\d+$/', $value)) {
                    throw new \InvalidArgumentException(
                        'A branch alias consists of major and minor version without any prefix or suffix. like: 1.2'
                    );
                }

                return $value . '-dev';
            }
        );

        $this->git->setGitConfig('branch.' . $branch . '.alias', $label, true);
        $this->style->note(
            [
                'Branch-alias is stored for feature reference.',
                'You can change this any time using the `branch-alias` command.',
            ]
        );

        return $label;
    }
}
