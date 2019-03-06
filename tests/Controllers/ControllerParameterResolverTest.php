<?php

/*
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (c) 2019 David Young
 * @license   https://github.com/aphiria/api/blob/master/LICENSE.md
 */

namespace Aphiria\Api\Tests\Controllers;

use Aphiria\Api\Controllers\ControllerParameterResolver;
use Aphiria\Api\Controllers\MissingControllerParameterValueException;
use Aphiria\Api\Tests\Controllers\Mocks\Controller;
use Aphiria\Api\Tests\Controllers\Mocks\User;
use Aphiria\Net\Http\ContentNegotiation\ContentNegotiationResult;
use Aphiria\Net\Http\ContentNegotiation\IContentNegotiator;
use Aphiria\Net\Http\ContentNegotiation\MediaTypeFormatters\IMediaTypeFormatter;
use Aphiria\Net\Http\IHttpRequestMessage;
use Aphiria\Net\Http\Request;
use Aphiria\Net\Http\StringBody;
use Aphiria\Net\Uri;
use Aphiria\Serialization\SerializationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;

/**
 * Tests the controller parameter resolver
 */
class ControllerParameterResolverTest extends TestCase
{
    /** @var ControllerParameterResolver The resolver to use in tests */
    private $resolver;
    /** @var IContentNegotiator|MockObject The content negotiator */
    private $contentNegotiator;

    protected function setUp(): void
    {
        $this->contentNegotiator = $this->createMock(IContentNegotiator::class);
        $this->resolver = new ControllerParameterResolver($this->contentNegotiator);
    }

    public function testResolvingParameterWithNoTypeHintUsesVariableFromRoute(): void
    {
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'noTypeHintParameter'], 'foo'),
            $this->createMock(IHttpRequestMessage::class),
            ['foo' => 'bar']
        );
        $this->assertEquals('bar', $resolvedParameter);
    }

    public function testResolvingNullableObjectParameterWithBodyThatCannotDeserializeToTypePassesNull(): void
    {
        $request = $this->createRequestWithoutBody('http://foo.com');
        $request->setBody(new StringBody('dummy body'));
        /** @var IMediaTypeFormatter|MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $mediaTypeFormatter->expects($this->once())
            ->method('readFromStream')
            ->with($request->getBody()->readAsStream(), User::class)
            ->willThrowException(new SerializationException);
        $this->contentNegotiator->expects($this->once())
            ->method('negotiateRequestContent')
            ->with(User::class, $request)
            ->willReturn(new ContentNegotiationResult($mediaTypeFormatter, null, null, null));
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'nullableObjectParameter'], 'user'),
            $request,
            []
        );
        $this->assertNull($resolvedParameter);
    }

    public function testResolvingNullableObjectParameterWithoutBodyPassesNull(): void
    {
        $request = $this->createRequestWithoutBody('http://foo.com');
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'nullableObjectParameter'], 'user'),
            $request,
            []
        );
        $this->assertNull($resolvedParameter);
    }

    public function testResolvingNullableScalarParameterWithNoMatchingValuePassesNull(): void
    {
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'nullableScalarParameter'], 'foo'),
            $this->createRequestWithoutBody('http://foo.com'),
            []
        );
        $this->assertNull($resolvedParameter);
    }

    public function testResolvingObjectParameterAndNoRequestBodyThrowsException(): void
    {
        $this->expectException(MissingControllerParameterValueException::class);
        $this->expectExceptionMessage('Body is null when resolving parameter user');
        $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'objectParameter'], 'user'),
            $this->createRequestWithoutBody('http://foo.com'),
            []
        );
    }

    public function testResolvingObjectParameterReadsFromRequestBodyFirst(): void
    {
        $request = $this->createRequestWithoutBody('http://foo.com');
        $request->setBody(new StringBody('dummy body'));
        $expectedUser = new User(123, 'foo@bar.com');
        /** @var IMediaTypeFormatter|MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $mediaTypeFormatter->expects($this->once())
            ->method('readFromStream')
            ->with($request->getBody()->readAsStream(), User::class)
            ->willReturn($expectedUser);
        $this->contentNegotiator->expects($this->once())
            ->method('negotiateRequestContent')
            ->with(User::class, $request)
            ->willReturn(new ContentNegotiationResult($mediaTypeFormatter, null, null, null));
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'objectParameter'], 'user'),
            $request,
            []
        );
        $this->assertEquals($expectedUser, $resolvedParameter);
    }

    public function testResolvingScalarParameterAndNoMatchingVariableThrowsException(): void
    {
        $this->expectException(MissingControllerParameterValueException::class);
        $this->expectExceptionMessage('No valid value for parameter foo');
        $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'stringParameter'], 'foo'),
            $this->createRequestWithoutBody('http://foo.com'),
            []
        );
    }

    public function testResolvingScalarParameterAndNoMatchingVariableUsesDefaultValueIfAvailable(): void
    {
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'defaultValueParameter'], 'foo'),
            $this->createRequestWithoutBody('http://foo.com'),
            []
        );
        $this->assertEquals('bar', $resolvedParameter);
    }

    public function testResolvingScalarParameterUsesMatchingQueryStringVariable(): void
    {
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'stringParameter'], 'foo'),
            $this->createRequestWithoutBody('http://foo.com/?foo=bar'),
            []
        );
        $this->assertEquals('bar', $resolvedParameter);
    }

    public function testResolvingScalarParameterUsesMatchingRouteVariableOverQueryStringVariable(): void
    {
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'stringParameter'], 'foo'),
            $this->createRequestWithoutBody('http://foo.com/?foo=baz'),
            ['foo' => 'dave']
        );
        $this->assertEquals('dave', $resolvedParameter);
    }

    /**
     * Creates a request with the input URI and no body
     *
     * @param string $uri The URI to use
     * @return Request The request
     */
    private function createRequestWithoutBody(string $uri): Request
    {
        return new Request('GET', new Uri($uri));
    }
}
