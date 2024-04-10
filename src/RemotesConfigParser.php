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

namespace HubKit;

/**
 * @internal
 */
final class RemotesConfigParser
{
    /**
     * @return array{main?: string, fork?: string}
     */
    public static function parse(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $lines = preg_split('{\r?\n}', $content) ?: [];
        $vars = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '' || $line[0] === ';') {
                continue;
            }

            if (! preg_match('/^(?P<name>[a-z+]+)\h*=\h*(?P<value>[a-z]+(?:_?[a-z]+)*?)$/', $line, $matches)) {
                throw new \InvalidArgumentException(sprintf('Unable to process file ".hk_remotes" at line %d, expected declaration (`name=value` or `name=va_lue`), empty line or comment (`; comment`), got: %s', $i + 1, $line));
            }

            if (isset($vars[$matches['name']])) {
                throw new \InvalidArgumentException(sprintf('Unable to process file ".hk_remotes" at line %d, declaration %s already set.', $i + 1, $matches['name']));
            }

            if ($matches['name'] !== 'main' && $matches['name'] !== 'fork') {
                throw new \InvalidArgumentException(sprintf('Unable to process file ".hk_remotes" at line %d, declaration %s is not accepted, only "main" or "fork".', $i + 1, $matches['name']));
            }

            $vars[$matches['name']] = $matches['value'];
        }

        return $vars;
    }
}
