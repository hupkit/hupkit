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

namespace HubKit\Tests\Handler;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

trait SymfonyStyleTrait
{
    /** @var ArrayInput */
    protected $input;
    /** @var ArrayInput */
    protected $output;

    /**
     * @param array $input
     *
     * @return SymfonyStyle
     */
    protected function createStyle(array $input = [])
    {
        $this->input = new ArrayInput([]);
        $this->input->setInteractive(true);

        if ($input) {
            $this->input->setStream($this->getInputStream($input));
        }

        $this->output = new StreamOutput(fopen('php://memory', 'wb', false));
        $this->output->setDecorated(false);

        return new SymfonyStyle($this->input, $this->output);
    }

    protected function getInputStream(array $input)
    {
        $input = implode(PHP_EOL, $input);

        $stream = fopen('php://memory', 'rb+', false);
        fwrite($stream, $input);
        rewind($stream);

        return $stream;
    }
}
