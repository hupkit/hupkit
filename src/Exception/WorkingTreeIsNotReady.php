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

namespace HubKit\Exception;

final class WorkingTreeIsNotReady extends \Exception
{
    public function __construct()
    {
        parent::__construct('The Git working tree is not ready. There are uncommitted changes or a rebase is in progress.');
    }
}
