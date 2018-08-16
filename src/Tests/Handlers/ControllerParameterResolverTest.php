<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2018 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Api\Tests\Handlers;

use Opulence\Api\Handlers\ControllerParameterResolver;
use Opulence\Api\Handlers\MissingControllerParameterValueException;
use Opulence\Api\RequestContext;
use Opulence\Api\Tests\Handlers\Mocks\Controller;
use Opulence\Api\Tests\Handlers\Mocks\User;
use Opulence\Net\Http\ContentNegotiation\ContentNegotiationResult;
use Opulence\Net\Http\ContentNegotiation\IMediaTypeFormatter;
use Opulence\Net\Http\Request;
use Opulence\Net\Http\StringBody;
use Opulence\Net\Uri;
use Opulence\Routing\Matchers\MatchedRoute;
use Opulence\Routing\RouteAction;
use Opulence\Serialization\SerializationException;
use ReflectionParameter;

/**
 * Tests the controller parameter resolver
 */
class ControllerParameterResolverTest extends \PHPUnit\Framework\TestCase
{
    /** @var ControllerParameterResolver The resolver to use in tests */
    private $resolver;

    public function setUp(): void
    {
        $this->resolver = new ControllerParameterResolver();
    }

    public function testResolvingParameterWithNoTypeHintUsesVariableFromRoute(): void
    {
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'noTypeHintParameter'], 'foo'),
            new RequestContext(
                $this->createRequest('http://foo.com'),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(
                    new RouteAction(Controller::class, 'noTypeHintParameter', null),
                    ['foo' => 'bar'],
                    []
                )
            )
        );
        $this->assertEquals('bar', $resolvedParameter);
    }

    public function testResolvingNullableObjectParameterWithBodyThatCannotDeserializeToTypePassesNull(): void
    {
        $request = $this->createRequest('http://foo.com');
        $request->setBody(new StringBody('dummy body'));
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $mediaTypeFormatter->expects($this->once())
            ->method('readFromStream')
            ->with($request->getBody()->readAsStream(), User::class)
            ->willThrowException(new SerializationException);
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'nullableObjectParameter'], 'user'),
            new RequestContext(
                $request,
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(new RouteAction(Controller::class, 'nullableObjectParameter', null), [], [])
            )
        );
        $this->assertNull($resolvedParameter);
    }

    public function testResolvingNullableObjectParameterWithoutBodyPassesNull(): void
    {
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $mediaTypeFormatter->expects($this->never())
            ->method('readFromStream');
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'nullableObjectParameter'], 'user'),
            new RequestContext(
                $this->createRequest('http://foo.com'),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(new RouteAction(Controller::class, 'nullableObjectParameter', null), [], [])
            )
        );
        $this->assertNull($resolvedParameter);
    }

    public function testResolvingNullableScalarParameterWithNoMatchingValuePassesNull(): void
    {
        $request = $this->createRequest('http://foo.com');
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'nullableScalarParameter'], 'foo'),
            new RequestContext(
                $request,
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(new RouteAction(Controller::class, 'nullableScalarParameter', null), [], [])
            )
        );
        $this->assertNull($resolvedParameter);
    }

    public function testResolvingObjectParameterAndNoRequestBodyThrowsException(): void
    {
        $this->expectException(MissingControllerParameterValueException::class);
        $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'objectParameter'], 'user'),
            new RequestContext(
                $this->createRequest('http://foo.com'),
                new ContentNegotiationResult(null, null, null, null),
                new ContentNegotiationResult(null, null, null, null),
                new MatchedRoute(new RouteAction(Controller::class, 'objectParameter', null), [], [])
            )
        );
    }

    public function testResolvingObjectParameterReadsFromRequestBodyFirst(): void
    {
        $request = $this->createRequest('http://foo.com');
        $request->setBody(new StringBody('dummy body'));
        $expectedUser = new User(123, 'foo@bar.com');
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $mediaTypeFormatter->expects($this->once())
            ->method('readFromStream')
            ->with($request->getBody()->readAsStream(), User::class)
            ->willReturn($expectedUser);
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'objectParameter'], 'user'),
            new RequestContext(
                $request,
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(new RouteAction(Controller::class, 'objectParameter', null), [], [])
            )
        );
        $this->assertEquals($expectedUser, $resolvedParameter);
    }

    public function testResolvingScalarParameterAndNoMatchingVariableThrowsException(): void
    {
        $this->expectException(MissingControllerParameterValueException::class);
        $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'stringParameter'], 'foo'),
            new RequestContext(
                $this->createRequest('http://foo.com'),
                new ContentNegotiationResult(null, null, null, null),
                new ContentNegotiationResult(null, null, null, null),
                new MatchedRoute(new RouteAction(Controller::class, 'stringParameter', null), [], [])
            )
        );
    }

    public function testResolvingScalarParameterAndNoMatchingVariableUsesDefaultValueIfAvailable(): void
    {
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'defaultValueParameter'], 'foo'),
            new RequestContext(
                $this->createRequest('http://foo.com'),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(new RouteAction(Controller::class, 'defaultValueParameter', null), [], [])
            )
        );
        $this->assertEquals('bar', $resolvedParameter);
    }

    public function testResolvingScalarParameterUsesMatchingQueryStringVariable(): void
    {
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'stringParameter'], 'foo'),
            new RequestContext(
                $this->createRequest('http://foo.com/?foo=bar'),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(new RouteAction(Controller::class, 'stringParameter', null), [], [])
            )
        );
        $this->assertEquals('bar', $resolvedParameter);
    }

    public function testResolvingScalarParameterUsesMatchingRouteVariableOverQueryStringVariable(): void
    {
        /** @var IMediaTypeFormatter|\PHPUnit_Framework_MockObject_MockObject $mediaTypeFormatter */
        $mediaTypeFormatter = $this->createMock(IMediaTypeFormatter::class);
        $resolvedParameter = $this->resolver->resolveParameter(
            new ReflectionParameter([Controller::class, 'stringParameter'], 'foo'),
            new RequestContext(
                $this->createRequest('http://foo.com/?foo=baz'),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new ContentNegotiationResult($mediaTypeFormatter, null, null, null),
                new MatchedRoute(new RouteAction(Controller::class, 'stringParameter', null), ['foo' => 'dave'], [])
            )
        );
        $this->assertEquals('dave', $resolvedParameter);
    }

    /**
     * Creates a request with the input URI
     *
     * @param string $uri The URI to use
     * @return Request The request
     */
    private function createRequest(string $uri): Request
    {
        return new Request('GET', new Uri($uri));
    }
}
