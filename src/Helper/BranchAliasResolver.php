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

namespace HubKit\Helper;

use HubKit\Config;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use Symfony\Component\Console\Style\StyleInterface;

class BranchAliasResolver
{
    private string $detectedBy = '';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly StyleInterface $style,
        private readonly Git $git,
        private readonly Config $config,
    ) {}

    public function getAlias(?string $branch = null): string
    {
        $branch ??= $this->git->getActiveBranchName();
        $alias = $this->getAliasByComposer($branch);

        if ($alias !== '') {
            $this->detectedBy = 'composer.json "extra.branch-alias.dev-' . $branch . '"';

            return $alias;
        }

        $alias = $this->config->getForRepository()['branches_alias'][$branch] ?? '';

        if ($alias !== '') {
            $this->detectedBy = 'configuration [branches_alias][' . $branch . ']';

            return $alias;
        }

        $this->detectedBy = 'Git config "branch.' . $branch . '.alias"';
        $alias = $this->git->getGitConfig('branch.' . $branch . '.alias');

        $this->style->caution(
            sprintf(
                'Usage of %s is deprecated and will be removed in v2.0. Add either an "extra.branch-alias.dev-%s" in composer.json or add branches_alias.%2$s to the repository local configuration.',
                $this->detectedBy,
                $branch
            )
        );

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
        if (! $this->filesystem->fileExists('./composer.json')) {
            return '';
        }

        $composer = json_decode($this->filesystem->getFileContents('./composer.json'), true, 512, \JSON_THROW_ON_ERROR);

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
                if (! preg_match('/^([1-9]\d*\.\d+)$/', (string) $value)) {
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
