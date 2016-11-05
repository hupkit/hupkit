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
    public function handle(Args $args)
    {
        $this->informationHeader();

        $issue = $this->github->getIssue(
            $args->getArgument('number')
        );

        if (isset($issue['pull_request'])) {
            throw new \InvalidArgumentException('Cannot take pull-request issue.');
        }

        if ('closed' === $issue['state']) {
            throw new \InvalidArgumentException('Cannot take closed issue.');
        }

        $slugTitle = StringUtil::slugify(sprintf('%s %s', $issue['number'], $issue['title']));

        $this->git->remoteUpdate('upstream');
        $this->git->checkout('upstream/'.$args->getOption('base'));
        $this->git->checkout($slugTitle, true);

        $this->style->success(sprintf('Issue %s taken!', $issue['html_url']));
    }
}
