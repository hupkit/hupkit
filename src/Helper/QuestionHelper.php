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

namespace HubKit\Helper;

use Symfony\Component\Console\Helper\QuestionHelper as BaseQuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class QuestionHelper extends BaseQuestionHelper
{
    private ?int $maxAttempts = null;

    /**
     * Overwrite the maximum attempts for a question.
     *
     * This method is only meant for testing, as
     * no limit hangs the test forever..
     *
     * @return QuestionHelper
     */
    public function setMaxAttempts(int $attempts)
    {
        $this->maxAttempts = $attempts;

        return $this;
    }

    public function ask(InputInterface $input, OutputInterface $output, Question $question): mixed
    {
        if ($this->maxAttempts !== null) {
            $question->setMaxAttempts($this->maxAttempts);
        }

        return parent::ask($input, $output, $question);
    }
}
