<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2020 David Young
 * @license   https://github.com/aphiria/aphiria/blob/0.x/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Console\Commands\Defaults;

use Aphiria\Console\Commands\Command;

/**
 * Defines the about command
 */
final class AboutCommand extends Command
{
    public function __construct()
    {
        parent::__construct(
            'about',
            [],
            [],
            'Describes the Aphiria console application'
        );
    }
}
