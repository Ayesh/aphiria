<?php

/*
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/console/blob/master/LICENSE.md
 */

namespace Aphiria\Console\Tests\Commands;

use Aphiria\Console\Commands\ClosureCommandHandler;
use Aphiria\Console\Input\Input;
use Aphiria\Console\Output\IOutput;
use Aphiria\Console\StatusCodes;
use PHPUnit\Framework\TestCase;

/**
 * Tests the closure command handler
 */
class ClosureCommandHandlerTest extends TestCase
{
    public function testHandlingInvokesClosure(): void
    {
        $closureIsInvoked = false;
        $closure = function (Input $input, IOutput $output) use (&$closureIsInvoked) {
            $closureIsInvoked = true;

            return StatusCodes::OK;
        };
        (new ClosureCommandHandler($closure))->handle(new Input('', [], []), $this->createMock(IOutput::class));
        $this->assertTrue($closureIsInvoked);
    }
}
