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
    private $style;
    private $git;
    private $cwd;
    private $detectedBy = '';

    public function __construct(StyleInterface $style, Git $git, string $cwd = null)
    {
        $this->style = $style;
        $this->git = $git;
        $this->cwd = $cwd ?? getcwd();
    }

    public function getAlias(): string
    {
        if (file_exists($this->cwd.'/composer.json') && '' !== $alias = $this->getAliasByComposer()) {
            $this->detectedBy = 'composer.json "extra.branch-alias.dev-master"';

            return $alias;
        }

        $this->detectedBy = 'Git config "branch.master.alias"';

        if ('' !== ($alias = $this->git->getGitConfig('branch.master.alias'))) {
            return $alias;
        }

        return $this->askNewAlias();
    }

    public function getDetectedBy(): string
    {
        return $this->detectedBy;
    }

    private function getAliasByComposer(): string
    {
        $composer = json_decode(file_get_contents($this->cwd.'/composer.json'), true);

        if (!isset($composer['extra']['branch-alias']['dev-master'])) {
            return '';
        }

        $label = $composer['extra']['branch-alias']['dev-master'];

        // Unstable releases are known to change often so use `1.0-dev` as final destination
        if ('0' === $label[0]) {
            $label = '1.0-dev';
        }

        return $label;
    }

    private function askNewAlias(): string
    {
        $this->style->note(
            [
                'No branch-alias found for "master", please provide an alias.',
                'This should be the version the master will become.',
                'If the last release is 2.1 the next will be eg. 2.2 or 3.0.',
            ]
        );

        $label = (string) $this->style->ask(
            'Branch alias',
            null,
            function ($value) {
                if (!preg_match('/^\d+\.\d+$/', $value)) {
                    throw new \InvalidArgumentException(
                        'A branch alias consists of major and minor version without any prefix or suffix. like: 1.2'
                    );
                }

                return $value.'-dev';
            }
        );

        $this->git->setGitConfig('branch.master.alias', $label, true);
        $this->style->note(
            [
                'Branch-alias is stored for feature reference.',
                'You can change this any time using the `branch-alias` command.',
            ]
        );

        return $label;
    }
}
