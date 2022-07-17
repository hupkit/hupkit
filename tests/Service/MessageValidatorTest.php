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

namespace HubKit\Tests\Service;

use HubKit\Service\MessageValidator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MessageValidatorTest extends TestCase
{
    /** @test */
    public function it_validates_messages(): void
    {
        self::assertEquals([], MessageValidator::validateCommitsMessages([$this->createMessage('This is a fine message')]));
        self::assertEquals(
            [
                [MessageValidator::SEVERITY_HIGH, 'Description contains unacceptable contents', 'But this. is fucked-up.'],
            ],
            MessageValidator::validateCommitsMessages([
                $this->createMessage('This is a fine message'),
                $this->createMessage('But this. is fucked-up.'),
            ])
        );
    }

    /** @test */
    public function it_validates_message(): void
    {
        // Please. Don't judge by my swearing.
        $this->assertSeverityHigh('Fuck this piece of....');
        $this->assertSeverityHigh('Oh Shit.');
        $this->assertSeverityHigh('Oh crAp.');
        $this->assertSeverityHigh('damn. damn, damn');
        $this->assertSeverityHigh('Oh for fuck sake you piece of shit');
        $this->assertSeverityHigh('dammit, why u no work!?');
        $this->assertSeverityHigh('U son of bitch, why u no work!?');
        $this->assertSeverityHigh('Fus ro dah!');
        $this->assertSeverityHigh("This is fine\n\nNo. it's not fine, FUCK!");

        // Acceptable, but not OK.
        $this->assertSeverityMid('why u no work');
        $this->assertSeverityMid('why u suck');
        $this->assertSeverityMid('WIP');
        $this->assertSeverityMid('Whoops');
        $this->assertSeverityMid('oops');
        $this->assertSeverityMid('WAT THE?');
        $this->assertSeverityMid('Hell, upon thee?');
        $this->assertSeverityMid('Note: Needs clean-up');
        $this->assertSeverityMid('Note: Needs cleanup');
        $this->assertSeverityMid('OH: Needs cleanup');
        $this->assertSeverityMid('work in progress');
        $this->assertSeverityMid('Aah');
        $this->assertSeverityMid('Aahgrrrrr');

        // Acceptable
        $this->assertAcceptable('Fus');
        $this->assertAcceptable('Ship');
        $this->assertAcceptable('Piece of nope');
    }

    private function assertSeverityHigh(string $message): void
    {
        self::assertEquals(
            [MessageValidator::SEVERITY_HIGH, 'Description contains unacceptable contents', $message],
            MessageValidator::validateMessage($message),
            sprintf('Message "%s" should be considered unacceptable', $message)
        );
    }

    private function assertSeverityMid(string $message): void
    {
        self::assertEquals(
            [MessageValidator::SEVERITY_MID, 'Unrelated commits or work in progress?', $message],
            MessageValidator::validateMessage($message),
            sprintf('Message "%s" should be considered unacceptable', $message)
        );
    }

    private function assertAcceptable(string $message): void
    {
        self::assertNull(
            MessageValidator::validateMessage($message),
            sprintf('Message "%s" should be considered acceptable', $message)
        );
    }

    private function createMessage($message): array
    {
        return ['commit' => ['message' => $message]];
    }
}
