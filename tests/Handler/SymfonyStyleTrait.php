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

use PHPUnit\Framework\Assert;
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
     * @return SymfonyStyle
     */
    protected function createStyle(array $input = [])
    {
        $this->input = new ArrayInput([]);
        $this->input->setInteractive(true);

        if ($input) {
            $this->input->setStream($this->getInputStream($input));
        }

        $this->output = new StreamOutput(fopen('php://memory', 'w', false));
        $this->output->setDecorated(false);

        return new SymfonyStyle($this->input, $this->output);
    }

    protected function getInputStream(array $input)
    {
        $input = implode(\PHP_EOL, $input);

        $stream = fopen('php://memory', 'b+r', false);
        fwrite($stream, $input);
        rewind($stream);

        return $stream;
    }

    protected function getDisplay(bool $normalize = true)
    {
        rewind($this->output->getStream());

        $display = stream_get_contents($this->output->getStream());

        if ($normalize) {
            $display = str_replace(\PHP_EOL, "\n", $display);
        }

        return $display;
    }

    protected function assertOutputMatches($expectedLines, string $output = null, $regex = false): void
    {
        if ($output === null) {
            $output = $this->getDisplay();
        }

        $output = preg_replace('/\s!\s/', ' ', trim($output));
        $expectedLines = (array) $expectedLines;

        foreach ($expectedLines as $matchLine) {
            if (\is_array($matchLine)) {
                $line = $matchLine[0];
                $lineRegex = $matchLine[1];
            } else {
                $line = $matchLine;
                $lineRegex = $regex;
            }

            if (! $lineRegex) {
                $line = preg_replace('#\s+#', '\\s+', preg_quote($line, '#'));
            }

            Assert::assertMatchesRegularExpression('#' . $line . '#m', $output);
        }
    }

    protected function assertOutputNotMatches($lines, string $output = null, $regex = false): void
    {
        if ($output === null) {
            $output = $this->getDisplay();
        }

        $output = preg_replace('/\s!\s/', ' ', trim($output));
        $lines = (array) $lines;

        foreach ($lines as $matchLine) {
            if (\is_array($matchLine)) {
                $line = $matchLine[0];
                $lineRegex = $matchLine[1];
            } else {
                $line = $matchLine;
                $lineRegex = $regex;
            }

            if (! $lineRegex) {
                $line = preg_replace('#\s+#', '\\s+', preg_quote($line, '#'));
            }

            Assert::assertDoesNotMatchRegularExpression('#' . $line . '#m', $output);
        }
    }

    protected function assertNoOutput(string $output = null): void
    {
        if ($output === null) {
            $output = $this->getDisplay();
        }

        Assert::assertEquals('', $output);
    }
}
