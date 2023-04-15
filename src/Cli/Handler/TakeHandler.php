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

use HubKit\StringUtil;
use Webmozart\Console\Api\Args\Args;

final class TakeHandler extends GitBaseHandler
{
    public function handle(Args $args): void
    {
        $this->informationHeader();

        $issue = $this->github->getIssue(
            $args->getArgument('number')
        );

        if (isset($issue['pull_request'])) {
            throw new \InvalidArgumentException('Cannot take pull-request issue.');
        }

        if ($issue['state'] === 'closed') {
            throw new \InvalidArgumentException('Cannot take closed issue.');
        }

        $slugTitle = StringUtil::slugify(sprintf('%s %s', $issue['number'], $issue['title']));
        $base = $args->getOption('base') ?? $this->git->getPrimaryBranch();

        if ($this->git->branchExists($slugTitle)) {
            $this->style->warning('Branch already exists, checking out existing branch instead.');
            $this->git->checkout($slugTitle);

            return;
        }

        $this->git->remoteUpdate('upstream');
        $this->git->checkoutRemoteBranch('upstream', $base);
        $this->git->checkout($slugTitle, true);

        $this->style->success(sprintf('Issue %s taken with base "%s"!', $issue['html_url'], $base));
    }
}
