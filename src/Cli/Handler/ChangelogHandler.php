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

use HubKit\Helper\ChangelogRenderer;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class ChangelogHandler extends GitBaseHandler
{
    private $renderer;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github)
    {
        parent::__construct($style, $git, $github);
        $this->renderer = new ChangelogRenderer($git, $github);
    }

    public function handle(Args $args, IO $io): void
    {
        [$base, $head] = $this->resolveCommitRange($args);
        $this->informationHeader($head);

        if ($args->getOption('oneline')) {
            $io->writeLine($this->renderer->renderChangelogOneLine($base, $head));
        } else {
            $io->writeLine($this->renderer->renderChangelogByCategories($base, $head, ! $args->getOption('all')));
        }
    }

    private function resolveCommitRange(Args $args): array
    {
        if (! ($ref = $args->getArgument('ref'))) {
            $base = $this->git->getLastTagOnBranch();
            $head = $this->git->getActiveBranchName();

            return [$base, $head];
        }

        return $this->getRefRange($ref);
    }

    private function getRefRange(string $ref): array
    {
        if (mb_strpos($ref, '..', 1) === false || \count($points = explode('..', $ref)) !== 2) {
            throw new \InvalidArgumentException('missing ref range `base..head` or illegal offset given');
        }

        return $points;
    }
}
