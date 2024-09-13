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
use Symfony\Component\Console\Style\StyleInterface;

class BranchAliasResolver
{
    private string $detectedBy = '';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly StyleInterface $style,
        private readonly Config $config,
    ) {}

    public function getAlias(string $branch): string
    {
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

        if ($alias !== '') {
            return $alias;
        }

        $this->detectedBy = 'user input';

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

        $this->style->note(\sprintf('Set a value for config [branches_alias][%s] to prevent asking this question in the future.', $branch));

        return $label;
    }
}
