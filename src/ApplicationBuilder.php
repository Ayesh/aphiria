<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/configuration/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Configuration;

use Aphiria\Console\Commands\CommandRegistry;
use Aphiria\Routing\Builders\RouteBuilderRegistry;
use Aphiria\Routing\LazyRouteFactory;
use Closure;
use Opulence\Ioc\Bootstrappers\Bootstrapper;
use Opulence\Ioc\Bootstrappers\IBootstrapperDispatcher;
use Opulence\Ioc\IContainer;

/**
 * Defines an application builder
 */
class ApplicationBuilder implements IApplicationBuilder
{
    /** @var IContainer The DI container that will be used to resolve dependencies */
    private $container;
    /** @var IBootstrapperDispatcher The dispatcher for bootstrappers */
    private $bootstrapperDispatcher;
    /** @var Closure[] The list of bootstrapper callbacks */
    private $bootstrapperCallbacks = [];
    /** @var Closure[] The list of route callbacks */
    private $routeCallbacks = [];
    /** @var Closure[] The list of command callbacks */
    private $commandCallbacks = [];

    /**
     * @param IContainer $container The DI container that will be used to resolve dependencies
     * @param IBootstrapperDispatcher $bootstrapperDispatcher The bootstrapper dispatcher
     */
    public function __construct(IContainer $container, IBootstrapperDispatcher $bootstrapperDispatcher)
    {
        $this->container = $container;
        $this->bootstrapperDispatcher = $bootstrapperDispatcher;
    }

    /**
     * @inheritdoc
     */
    public function build(): void
    {
        /** @var Bootstrapper[] $bootstrappers */
        $bootstrappers = [];

        foreach ($this->bootstrapperCallbacks as $bootstrapperCallback) {
            foreach ((array)$bootstrapperCallback() as $bootstrapper) {
                $bootstrappers[] = $bootstrapper;
            }
        }

        $this->bootstrapperDispatcher->dispatch($bootstrappers);

        /**
         * We're specifically checking for a binding and, if it doesn't exist, adding a binding
         * This will make sure that the instances we use here are the same used elsewhere when resolving the interfaces
         */
        if ($this->container->hasBinding(LazyRouteFactory::class)) {
            $routeFactory = $this->container->resolve(LazyRouteFactory::class);
        } else {
            $this->container->bindInstance(LazyRouteFactory::class, $routeFactory = new LazyRouteFactory());
        }

        $routeFactory->addFactory(function () {
            $routeBuilders = new RouteBuilderRegistry();

            foreach ($this->routeCallbacks as $routeCallback) {
                $routeCallback($routeBuilders);
            }

            return $routeBuilders->buildAll();
        });

        if ($this->container->hasBinding(CommandRegistry::class)) {
            $commands = $this->container->resolve(CommandRegistry::class);
        } else {
            $this->container->bindInstance(CommandRegistry::class, $commands = new CommandRegistry());
        }

        foreach ($this->commandCallbacks as $commandCallback) {
            $commandCallback($commands);
        }
    }

    /**
     * @inheritdoc
     */
    public function withBootstrappers(Closure $callback): IApplicationBuilder
    {
        $this->bootstrapperCallbacks[] = $callback;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function withCommands(Closure $callback): IApplicationBuilder
    {
        $this->commandCallbacks[] = $callback;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function withModule(IModuleBuilder $moduleBuilder): IApplicationBuilder
    {
        $moduleBuilder->build($this);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function withRoutes(Closure $callback): IApplicationBuilder
    {
        $this->routeCallbacks[] = $callback;

        return $this;
    }
}
