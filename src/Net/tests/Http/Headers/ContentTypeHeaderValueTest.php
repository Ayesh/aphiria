<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2020 David Young
 * @license   https://github.com/aphiria/aphiria/blob/0.x/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Net\Tests\Http\Headers;

use Aphiria\Collections\IImmutableDictionary;
use Aphiria\Collections\ImmutableHashTable;
use Aphiria\Collections\KeyValuePair;
use Aphiria\Net\Http\Headers\ContentTypeHeaderValue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ContentTypeHeaderValueTest extends TestCase
{
    public function testGettingCharsetReturnsOneSetInConstructor(): void
    {
        $parameters = new ImmutableHashTable([new KeyValuePair('charset', 'utf-8')]);
        $value = new ContentTypeHeaderValue('foo/bar', $parameters);
        $this->assertSame('utf-8', $value->getCharset());
    }

    public function testGettingMediaTypeReturnsOneSetInConstructor(): void
    {
        $parameters = new ImmutableHashTable([new KeyValuePair('charset', 'utf-8')]);
        $value = new ContentTypeHeaderValue('foo/bar', $parameters);
        $this->assertSame('foo/bar', $value->getMediaType());
    }

    public function testGettingSubTypeReturnsCorrectSubtType(): void
    {
        $value = new ContentTypeHeaderValue('foo/bar', $this->createMock(IImmutableDictionary::class));
        $this->assertSame('bar', $value->getSubType());
    }

    public function testGettingTypeReturnsCorrectType(): void
    {
        $value = new ContentTypeHeaderValue('foo/bar', $this->createMock(IImmutableDictionary::class));
        $this->assertSame('foo', $value->getType());
    }

    public function testTypeWithSuffixSetsTypeSubTypeAndSuffixesCorrectly(): void
    {
        $value = new ContentTypeHeaderValue('application/foo+json', $this->createMock(IImmutableDictionary::class));
        $this->assertSame('application', $value->getType());
        $this->assertSame('foo+json', $value->getSubType());
        $this->assertSame('foo', $value->getSubTypeWithoutSuffix());
        $this->assertSame('json', $value->getSuffix());
    }

    public function incorrectlyFormattedMediaTypeProvider(): array
    {
        return [
            ['foo'],
            ['foo/'],
        ];
    }

    /**
     * @dataProvider incorrectlyFormattedMediaTypeProvider
     * @param string $incorrectlyFormattedMediaType The incorrectly-formatted media type
     */
    public function testIncorrectlyFormattedMediaTypeThrowsException(string $incorrectlyFormattedMediaType): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Media type must be in format {type}/{sub-type}, received {$incorrectlyFormattedMediaType}");
        new ContentTypeHeaderValue($incorrectlyFormattedMediaType, $this->createMock(IImmutableDictionary::class));
    }
}
