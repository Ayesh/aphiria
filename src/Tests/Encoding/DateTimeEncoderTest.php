<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (c) 2019 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Serialization\Tests\Encoding;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Opulence\Serialization\Encoding\DateTimeEncoder;
use Opulence\Serialization\Encoding\EncodingContext;
use PHPUnit\Framework\TestCase;

/**
 * Tests the DateTime encoder
 */
class DateTimeEncoderTest extends TestCase
{
    /** @var DateTimeEncoder The encoder to test */
    private $dateTimeEncoder;

    public function setUp(): void
    {
        $this->dateTimeEncoder = new DateTimeEncoder();
    }

    public function testDecodingDateTimeCreatesDateTime(): void
    {
        $encodedValue = (new DateTime)->format(DateTime::ATOM);
        $value = $this->dateTimeEncoder->decode($encodedValue, DateTime::class, new EncodingContext());
        $this->assertInstanceOf(DateTime::class, $value);
    }

    public function testDecodingDateTimeImmutableCreatesDateTimeImmutable(): void
    {
        $encodedValue = (new DateTimeImmutable)->format(DateTime::ATOM);
        $value = $this->dateTimeEncoder->decode($encodedValue, DateTimeImmutable::class, new EncodingContext());
        $this->assertInstanceOf(DateTimeImmutable::class, $value);
    }

    public function testDecodingDateTimeInterfaceCreatesDateTimeImmutable(): void
    {
        $encodedValue = (new DateTime)->format(DateTime::ATOM);
        $value = $this->dateTimeEncoder->decode($encodedValue, DateTimeInterface::class, new EncodingContext());
        $this->assertInstanceOf(DateTimeImmutable::class, $value);
    }

    public function testDecodingNonDateTimeTypesThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Type must be DateTime, DateTimeImmutable, or DateTimeInterface');
        $this->dateTimeEncoder->decode(123, 'foo', new EncodingContext());
    }

    public function testEncodingDateTimeReturnsFormattedString(): void
    {
        $dateTime = new DateTime();
        $this->assertEquals(
            $dateTime->format(DateTime::ATOM),
            $this->dateTimeEncoder->encode($dateTime, new EncodingContext())
        );
    }

    public function testEncodingNonDateTimeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must implement DateTimeInterface');
        $this->dateTimeEncoder->encode('foo', new EncodingContext());
    }
}
