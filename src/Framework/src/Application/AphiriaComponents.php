<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2020 David Young
 * @license   https://github.com/aphiria/aphiria/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Framework\Application;

use Aphiria\Application\Builders\IApplicationBuilder;
use Aphiria\Console\Commands\CommandRegistry;
use Aphiria\DependencyInjection\Binders\Binder;
use Aphiria\DependencyInjection\Binders\IBinderDispatcher;
use Aphiria\DependencyInjection\Container;
use Aphiria\DependencyInjection\ResolutionException;
use Aphiria\Framework\Console\Components\CommandComponent;
use Aphiria\Framework\DependencyInjection\Components\BinderComponent;
use Aphiria\Framework\Exceptions\Components\ExceptionHandlerComponent;
use Aphiria\Framework\Middleware\Components\MiddlewareComponent;
use Aphiria\Framework\Routing\Components\RouterComponent;
use Aphiria\Framework\Serialization\Components\SerializerComponent;
use Aphiria\Framework\Validation\Components\ValidationComponent;
use Aphiria\Middleware\MiddlewareBinding;
use Aphiria\Middleware\MiddlewareCollection;
use Aphiria\Serialization\Encoding\IEncoder;
use Closure;

/**
 * Defines the trait that simplifies interacting with Aphiria components
 */
trait AphiriaComponents
{
    /**
     * Adds binders to the binder component
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @param Binder|Binder[] $binders The binder or list of binders to add
     * @return self For chaining
     * @throws ResolutionException Thrown if there was a problem resolving dependencies
     */
    private function withBinders(IApplicationBuilder $appBuilder, $binders): self
    {
        if (!$appBuilder->hasComponent(BinderComponent::class)) {
            $appBuilder->withComponent(
                new BinderComponent(Container::$globalInstance->resolve(IBinderDispatcher::class)),
                0
            );
        }

        $appBuilder->getComponent(BinderComponent::class)
            ->withBinders($binders);

        return $this;
    }

    /**
     * Enables console command annotations
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @return self For chaining
     */
    private function withCommandAnnotations(IApplicationBuilder $appBuilder): self
    {
        // Note: We are violating DRY here just so that we don't have confusing methods for enabling this component
        if (!$appBuilder->hasComponent(CommandComponent::class)) {
            // Bind the command registry here so that it can be used in the component
            if (!Container::$globalInstance->hasBinding(CommandRegistry::class)) {
                Container::$globalInstance->bindInstance(CommandRegistry::class, new CommandRegistry());
            }

            $appBuilder->withComponent(new CommandComponent(Container::$globalInstance));
        }

        $appBuilder->getComponent(CommandComponent::class)
            ->withAnnotations();

        return $this;
    }

    /**
     * Adds console commands to the command component
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @param Closure $callback The callback that takes in an instance of CommandRegistry to register commands to
     * @return self For chaining
     */
    private function withCommands(IApplicationBuilder $appBuilder, Closure $callback): self
    {
        // Note: We are violating DRY here just so that we don't have confusing methods for enabling this component
        if (!$appBuilder->hasComponent(CommandComponent::class)) {
            // Bind the command registry here so that it can be used in the component
            if (!Container::$globalInstance->hasBinding(CommandRegistry::class)) {
                Container::$globalInstance->bindInstance(CommandRegistry::class, new CommandRegistry());
            }

            $appBuilder->withComponent(new CommandComponent(Container::$globalInstance));
        }

        $appBuilder->getComponent(CommandComponent::class)
            ->withCommands($callback);

        return $this;
    }

    /**
     * Adds an encoder to the encoder component
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @param string $class The class whose encoder we're registering
     * @param IEncoder $encoder The encoder to register
     * @return self For chaining
     */
    private function withEncoder(IApplicationBuilder $appBuilder, string $class, IEncoder $encoder): self
    {
        if (!$appBuilder->hasComponent(SerializerComponent::class)) {
            $appBuilder->withComponent(new SerializerComponent(Container::$globalInstance));
        }

        $appBuilder->getComponent(SerializerComponent::class)
            ->withEncoder($class, $encoder);

        return $this;
    }

    /**
     * Adds the exception handler middleware to the beginning of the middleware collection
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @return self For chaining
     */
    private function withExceptionHandlerMiddleware(IApplicationBuilder $appBuilder): self
    {
        // Note: We are violating DRY here just so that we don't have confusing methods for enabling this component
        if (!$appBuilder->hasComponent(ExceptionHandlerComponent::class)) {
            $appBuilder->withComponent(new ExceptionHandlerComponent(Container::$globalInstance, $appBuilder));
        }

        $appBuilder->getComponent(ExceptionHandlerComponent::class)
            ->withExceptionHandlerMiddleware();

        return $this;
    }

    /**
     * Adds an exception response factory to the exception handler component
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @param string $exceptionType The type of exception whose response factory we're registering
     * @param Closure $responseFactory The factory that takes in an instance of the exception, ?IHttpRequestMessage, and INegotiatedResponseFactory and creates a response
     * @return self For chaining
     */
    private function withExceptionResponseFactory(IApplicationBuilder $appBuilder, string $exceptionType, Closure $responseFactory): self
    {
        // Note: We are violating DRY here just so that we don't have confusing methods for enabling this component
        if (!$appBuilder->hasComponent(ExceptionHandlerComponent::class)) {
            $appBuilder->withComponent(new ExceptionHandlerComponent(Container::$globalInstance, $appBuilder));
        }

        $appBuilder->getComponent(ExceptionHandlerComponent::class)
            ->withResponseFactory($exceptionType, $responseFactory);

        return $this;
    }

    /**
     * Adds global middleware bindings to the middleware component
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @param MiddlewareBinding|MiddlewareBinding[] $middlewareBindings The middleware binding or list of bindings to add
     * @param int|null $priority The optional priority to apply to the middleware (lower number => higher priority)
     * @return self For chaining
     * @throws ResolutionException Thrown if there was a problem resolving dependencies
     */
    private function withGlobalMiddleware(IApplicationBuilder $appBuilder, $middlewareBindings, int $priority = null): self
    {
        if (!$appBuilder->hasComponent(MiddlewareComponent::class)) {
            // Bind the middleware collection here so that it can be used in the component
            Container::$globalInstance->hasBinding(MiddlewareCollection::class)
                ? $middlewareCollection = Container::$globalInstance->resolve(MiddlewareCollection::class)
                : Container::$globalInstance->bindInstance(MiddlewareCollection::class, $middlewareCollection = new MiddlewareCollection());
            $appBuilder->withComponent(new MiddlewareComponent(Container::$globalInstance));
        }

        $appBuilder->getComponent(MiddlewareComponent::class)
            ->withGlobalMiddleware($middlewareBindings, $priority);

        return $this;
    }

    /**
     * Adds a log level factory to the exception handler component
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @param string $exceptionType The exception type whose factory we're registering
     * @param Closure $logLevelFactory The factory that takes in an instance of the exception and returns the PSR-3 log level
     * @return self For chaining
     */
    private function withLogLevelFactory(IApplicationBuilder $appBuilder, string $exceptionType, Closure $logLevelFactory): self
    {
        //Note: We are violating DRY here just so that we don't have confusing methods for enabling this component
        if (!$appBuilder->hasComponent(ExceptionHandlerComponent::class)) {
            $appBuilder->withComponent(new ExceptionHandlerComponent(Container::$globalInstance, $appBuilder));
        }

        $appBuilder->getComponent(ExceptionHandlerComponent::class)
            ->withLogLevelFactory($exceptionType, $logLevelFactory);

        return $this;
    }

    /**
     * Adds object constraints to the object constraints component
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @param Closure $callback The callback that takes in an instance of ObjectConstraintsRegistry to register object constraints to
     * @return self For chaining
     */
    private function withObjectConstraints(IApplicationBuilder $appBuilder, Closure $callback): self
    {
        // Note: We are violating DRY here just so that we don't have confusing methods for enabling this component
        if (!$appBuilder->hasComponent(ValidationComponent::class)) {
            $appBuilder->withComponent(new ValidationComponent(Container::$globalInstance));
        }

        $appBuilder->getComponent(ValidationComponent::class)
            ->withObjectConstraints($callback);

        return $this;
    }

    /**
     * Enables routing annotations
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @return self For chaining
     */
    private function withRouteAnnotations(IApplicationBuilder $appBuilder): self
    {
        // Note: We are violating DRY here just so that we don't have confusing methods for enabling this component
        if (!$appBuilder->hasComponent(RouterComponent::class)) {
            $appBuilder->withComponent(new RouterComponent(Container::$globalInstance));
        }

        $appBuilder->getComponent(RouterComponent::class)
            ->withAnnotations();

        return $this;
    }

    /**
     * Adds routes to the router component
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @param Closure $callback The callback that takes in an instance of RouteBuilderRegistry to register route builders to
     * @return self For chaining
     */
    private function withRoutes(IApplicationBuilder $appBuilder, Closure $callback): self
    {
        // Note: We are violating DRY here just so that we don't have confusing methods for enabling this component
        if (!$appBuilder->hasComponent(RouterComponent::class)) {
            $appBuilder->withComponent(new RouterComponent(Container::$globalInstance));
        }

        $appBuilder->getComponent(RouterComponent::class)
            ->withRoutes($callback);

        return $this;
    }

    /**
     * Enables Aphiria validation annotations
     *
     * @param IApplicationBuilder $appBuilder The app builder to decorate
     * @return self For chaining
     */
    private function withValidatorAnnotations(IApplicationBuilder $appBuilder): self
    {
        // Note: We are violating DRY here just so that we don't have confusing methods for enabling this component
        if (!$appBuilder->hasComponent(ValidationComponent::class)) {
            $appBuilder->withComponent(new ValidationComponent(Container::$globalInstance));
        }

        $appBuilder->getComponent(ValidationComponent::class)
            ->withAnnotations();

        return $this;
    }
}