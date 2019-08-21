<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/router/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Routing\Tests;

use Aphiria\Routing\MethodRouteAction;
use PHPUnit\Framework\TestCase;

/**
 * Tests the method route action
 */
class MethodRouteActionTest extends TestCase
{
    /** @const The name of the class used in our method action */
    private const CLASS_NAME = 'Foo';
    /** @const The name of the method used in our method action */
    private const METHOD_NAME = 'bar';
    /** @var MethodRouteAction An instance that uses a method as the action */
    private MethodRouteAction $methodAction;

    protected function setUp(): void
    {
        $this->methodAction = new MethodRouteAction(self::CLASS_NAME, self::METHOD_NAME);
    }

    public function testCorrectClassNameIsReturned(): void
    {
        $this->assertEquals(self::CLASS_NAME, $this->methodAction->className);
    }

    public function testCorrectMethodNameIsReturned(): void
    {
        $this->assertEquals(self::METHOD_NAME, $this->methodAction->methodName);
    }

    public function testMethodFlagSetCorrectly(): void
    {
        $this->assertTrue($this->methodAction->usesMethod());
    }

    public function testNullClosureIsReturnedByMethodAction(): void
    {
        $this->assertNull($this->methodAction->closure);
    }
}