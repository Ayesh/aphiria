<?php

/*
 * Opulence
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/console/blob/master/LICENSE.md
 */

namespace Aphiria\Console\Tests\Prompts\Questions;

use Aphiria\Console\Prompts\Questions\Confirmation;
use PHPUnit\Framework\TestCase;

/**
 * Tests the confirmation question
 */
class ConfirmationTest extends TestCase
{
    /** @var Confirmation */
    private $question;

    public function setUp(): void
    {
        $this->question = new Confirmation('Is Dave cool (yn)');
    }

    public function testFormattingFalseValues(): void
    {
        $this->assertFalse($this->question->formatAnswer('n'));
        $this->assertFalse($this->question->formatAnswer('N'));
        $this->assertFalse($this->question->formatAnswer('no'));
        $this->assertFalse($this->question->formatAnswer('NO'));
    }

    public function testFormattingTrueValues(): void
    {
        $this->assertTrue($this->question->formatAnswer('y'));
        $this->assertTrue($this->question->formatAnswer('Y'));
        $this->assertTrue($this->question->formatAnswer('yes'));
        $this->assertTrue($this->question->formatAnswer('YES'));
    }
}
