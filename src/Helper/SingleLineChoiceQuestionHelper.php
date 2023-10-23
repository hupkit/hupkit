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

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class SingleLineChoiceQuestionHelper extends QuestionHelper
{
    protected function writePrompt(OutputInterface $output, Question $question): void
    {
        if (! $question instanceof ChoiceQuestion) {
            parent::writePrompt($output, $question);

            return;
        }

        $text = OutputFormatter::escape($question->getQuestion());
        $text = sprintf(' <info>%s</info> ', $text);

        $default = $question->getDefault();
        $defaultLabel = '';

        if ($default !== null) {
            $defaultLabel = sprintf(' [<comment>%s</comment>] ', OutputFormatter::escape($default));
        }

        $output->write($text);
        $output->writeln('<info>(' . implode(', ', array_values($question->getChoices())) . ')?</>' . $defaultLabel);
        $output->write(' > ');
    }
}
