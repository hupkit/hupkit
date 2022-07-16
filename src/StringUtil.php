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

namespace HubKit;

use Ddd\Slug\Infra\SlugGenerator\DefaultSlugGenerator;
use Ddd\Slug\Infra\Transliterator\LatinTransliterator;
use Ddd\Slug\Infra\Transliterator\TransliteratorCollection;

class StringUtil
{
    /**
     * Split lines to an array.
     *
     * @return string[]
     */
    public static function splitLines(string $input): array
    {
        $input = trim($input);

        return ($input === '') ? [] : preg_split('{\r?\n}', $input);
    }

    /**
     * Concatenates the words to an uppercased wording.
     *
     * Converts 'git flow', 'git-flow' and 'git_flow' to 'GitFlow'.
     *
     * @param string $word The word to transform
     *
     * @return string The transformed word
     */
    public static function concatWords(string $word): string
    {
        return str_replace([' ', '-', '_'], '', ucwords($word, '_- '));
    }

    /**
     * Camelizes a word.
     *
     * This uses the classify() method and turns the first character to lowercase.
     *
     * @param string $word The word to camelize
     *
     * @return string The camelized word
     */
    public static function camelize(string $word): string
    {
        return lcfirst(self::concatWords($word));
    }

    /**
     * Slugify a string.
     */
    public static function slugify(string $string): string
    {
        return (new DefaultSlugGenerator(
            new TransliteratorCollection([new LatinTransliterator()]),
            []
        ))->slugify((array) $string);
    }
}
