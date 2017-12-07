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

namespace HubKit\Service;

use HubKit\StringUtil;

class MessageValidator
{
    public const SEVERITY_LOW = 0;
    public const SEVERITY_MID = 3;
    public const SEVERITY_HIGH = 5;

    public static function validateCommitsMessages(array $commits): array
    {
        $result = [];

        foreach ($commits as $commit) {
            if (null !== $validated = self::validateMessage($commit['commit']['message'])) {
                $result[] = $validated;
            }
        }

        return $result;
    }

    public static function validateMessage(string $message): ?array
    {
        // I wont judge you for swearing, but for merging commits this is unacceptable!
        if (preg_match('/fuck|shit|crap|damn|dammit|Fus ro dah|wtf|bitch/i', $message)) {
            return [self::SEVERITY_HIGH, 'Description contains unacceptable contents', $message];
        }

        $firstLine = StringUtil::splitLines($message)[0] ?? '';

        // 'could you'?? Yes... I call it the 'working late' detection.
        if (preg_match('/(\b(WIP|work in progress|clean-?up|(wh)?oops|could you|wat|hell)\b)|A{2,}h|(why u )|^OH:/i', $firstLine)) {
            return [self::SEVERITY_MID, 'Unrelated commits or work in progress?', $message];
        }

        return null;
    }
}
