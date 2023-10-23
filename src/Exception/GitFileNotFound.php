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

final class GitFileNotFound extends \RuntimeException
{
    public static function atBranch(string $branch, string $path): self
    {
        return new self(sprintf('File in Git local repository could not be found. In branch "%s" at path "%s".', $branch, $path));
    }

    public static function atRemote(string $remote, string $branch, string $path): self
    {
        return new self(sprintf('File in Git remote repository could not be found. For remote "%s" in branch "%s" at path "%s".', $remote, $branch, $path));
    }
}
