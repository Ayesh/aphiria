<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/aphiria/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\DependencyInjection;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Defines the dependency injection container
 */
class Container implements IContainer
{
    /** The value for an empty target */
    private static $emptyTarget;
    /** @var string|null The current target */
    protected ?string $currentTarget = null;
    /** @var array The stack of targets */
    protected array $targetStack = [];
    /** @var IContainerBinding[][] The list of bindings */
    protected array $bindings = [];
    /** @var array The cache of reflection constructors and their parameters */
    protected array $constructorReflectionCache = [];

    /**
     * Prepares the container for serialization
     */
    public function __sleep()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function bindFactory($interfaces, callable $factory, bool $resolveAsSingleton = false): void
    {
        $binding = new FactoryContainerBinding($factory, $resolveAsSingleton);

        foreach ((array)$interfaces as $interface) {
            $this->addBinding($interface, $binding);
        }
    }

    /**
     * @inheritdoc
     */
    public function bindInstance($interfaces, object $instance): void
    {
        $binding = new InstanceContainerBinding($instance);

        foreach ((array)$interfaces as $interface) {
            $this->addBinding($interface, $binding);
        }
    }

    /**
     * @inheritdoc
     */
    public function bindPrototype($interfaces, string $concreteClass = null, array $primitives = []): void
    {
        foreach ((array)$interfaces as $interface) {
            $this->addBinding($interface, new ClassContainerBinding($concreteClass ?? $interface, $primitives, false));
        }
    }

    /**
     * @inheritdoc
     */
    public function bindSingleton($interfaces, string $concreteClass = null, array $primitives = []): void
    {
        foreach ((array)$interfaces as $interface) {
            $this->addBinding($interface, new ClassContainerBinding($concreteClass ?? $interface, $primitives, true));
        }
    }

    /**
     * @inheritdoc
     */
    public function callClosure(callable $closure, array $primitives = [])
    {
        $unresolvedParameters = (new ReflectionFunction($closure))->getParameters();
        $resolvedParameters = $this->resolveParameters(null, $unresolvedParameters, $primitives);

        return $closure(...$resolvedParameters);
    }

    /**
     * @inheritdoc
     */
    public function callMethod($instance, string $methodName, array $primitives = [], bool $ignoreMissingMethod = false)
    {
        if (!method_exists($instance, $methodName)) {
            if (!$ignoreMissingMethod) {
                throw new DependencyInjectionException('Cannot call method');
            }

            return null;
        }

        $unresolvedParameters = (new ReflectionMethod($instance, $methodName))->getParameters();
        $className = is_string($instance) ? $instance : get_class($instance);
        $resolvedParameters = $this->resolveParameters($className, $unresolvedParameters, $primitives);

        return ([$instance, $methodName])(...$resolvedParameters);
    }

    /**
     * @inheritdoc
     */
    public function for(string $targetClass, callable $callback)
    {
        $this->currentTarget = $targetClass;
        $this->targetStack[] = $targetClass;

        $result = $callback($this);

        array_pop($this->targetStack);
        $this->currentTarget = end($this->targetStack) ?: self::$emptyTarget;

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function hasBinding(string $interface): bool
    {
        if ($this->currentTarget !== self::$emptyTarget
            && $this->hasTargetedBinding($interface, $this->currentTarget)
        ) {
            return true;
        }

        return $this->hasTargetedBinding($interface, self::$emptyTarget);
    }

    /**
     * @inheritdoc
     */
    public function resolve(string $interface): object
    {
        $binding = $this->getBinding($interface);

        if ($binding === null) {
            // Try just resolving this directly
            return $this->resolveClass($interface);
        }

        switch (get_class($binding)) {
            case InstanceContainerBinding::class:
                /** @var InstanceContainerBinding $binding */
                return $binding->getInstance();
            case ClassContainerBinding::class:
                /** @var ClassContainerBinding $binding */
                $instance = $this->resolveClass(
                    $binding->getConcreteClass(),
                    $binding->getConstructorPrimitives()
                );
                break;
            case FactoryContainerBinding::class:
                /** @var FactoryContainerBinding $binding */
                $factory = $binding->getFactory();
                $instance = $factory();
                break;
            default:
                throw new DependencyInjectionException('Invalid binding type "' . get_class($binding) . '"');
        }

        if ($binding->resolveAsSingleton()) {
            $this->unbind($interface);
            $this->addBinding($interface, new InstanceContainerBinding($instance));
        }

        return $instance;
    }

    /**
     * @inheritdoc
     */
    public function tryResolve(string $interface, &$instance): bool
    {
        try {
            $instance = $this->resolve($interface);

            return true;
        } catch (ResolutionException $ex) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function unbind($interfaces): void
    {
        foreach ((array)$interfaces as $interface) {
            unset($this->bindings[$this->currentTarget][$interface]);
        }
    }

    /**
     * Adds a binding to an interface
     *
     * @param string $interface The interface to bind to
     * @param IContainerBinding $binding The binding to add
     */
    protected function addBinding(string $interface, IContainerBinding $binding): void
    {
        if (!isset($this->bindings[$this->currentTarget])) {
            $this->bindings[$this->currentTarget] = [];
        }

        $this->bindings[$this->currentTarget][$interface] = $binding;
    }

    /**
     * Gets a binding for an interface
     *
     * @param string $interface The interface whose binding we want
     * @return IContainerBinding|null The binding if one exists, otherwise null
     */
    protected function getBinding(string $interface): ?IContainerBinding
    {
        // If there's a targeted binding, use it
        if ($this->currentTarget !== self::$emptyTarget && isset($this->bindings[$this->currentTarget][$interface])) {
            return $this->bindings[$this->currentTarget][$interface];
        }

        // If there's a universal binding, use it
        if (isset($this->bindings[self::$emptyTarget][$interface])) {
            return $this->bindings[self::$emptyTarget][$interface];
        }

        return null;
    }

    /**
     * Gets whether or not a targeted binding exists
     *
     * @param string $interface The interface to check
     * @param string|null $target The target whose bindings we're checking
     * @return bool True if the targeted binding exists, otherwise false
     */
    protected function hasTargetedBinding(string $interface, string $target = null): bool
    {
        return isset($this->bindings[$target][$interface]);
    }

    /**
     * Resolves a class
     *
     * @param string $class The class name to resolve
     * @param array $primitives The list of constructor primitives
     * @return object The resolved class
     * @throws DependencyInjectionException Thrown if the class could not be resolved
     */
    protected function resolveClass(string $class, array $primitives = []): object
    {
        try {
            if (isset($this->constructorReflectionCache[$class])) {
                [$constructor, $parameters] = $this->constructorReflectionCache[$class];
            } else {
                $reflectionClass = new ReflectionClass($class);

                if (!$reflectionClass->isInstantiable()) {
                    throw new ResolutionException(
                        $class,
                        $this->currentTarget,
                        sprintf(
                            '%s is not instantiable%s',
                            $class,
                            $this->currentTarget === null ? '' : " (dependency of {$this->currentTarget})"
                        )
                    );
                }

                $constructor = $reflectionClass->getConstructor();
                $parameters = $constructor !== null ? $constructor->getParameters() : null;
                $this->constructorReflectionCache[$class] = [$constructor, $parameters];
            }

            if ($constructor === null) {
                // No constructor, so instantiating is easy
                return new $class;
            }

            $constructorParameters = $this->resolveParameters($class, $parameters, $primitives);

            return new $class(...$constructorParameters);
        } catch (ReflectionException | DependencyInjectionException $ex) {
            throw new ResolutionException($class, $this->currentTarget, "Failed to resolve class $class", 0, $ex);
        }
    }

    /**
     * Resolves a list of parameters for a function call
     *
     * @param string|null $class The name of the class whose parameters we're resolving
     * @param ReflectionParameter[] $unresolvedParameters The list of unresolved parameters
     * @param array $primitives The list of primitive values
     * @return array The list of parameters with all the dependencies resolved
     * @throws DependencyInjectionException Thrown if there was an error resolving the parameters
     */
    protected function resolveParameters(
        $class,
        array $unresolvedParameters,
        array $primitives
    ): array {
        $resolvedParameters = [];

        foreach ($unresolvedParameters as $parameter) {
            $resolvedParameter = null;

            if ($parameter->getClass() === null) {
                // The parameter is a primitive
                $resolvedParameter = $this->resolvePrimitive($parameter, $primitives);
            } else {
                // The parameter is an object
                $parameterClassName = $parameter->getClass()->getName();

                /**
                 * We need to first check if the input class is a target for the parameter
                 * If it is, resolve it using the input class as a target
                 * Otherwise, attempt to resolve it universally
                 */
                if ($class !== null && $this->hasTargetedBinding($parameterClassName, $class)) {
                    $resolvedParameter = $this->for(
                        $class,
                        fn (IContainer $container) => $container->resolve($parameter->getClass()->getName())
                    );
                } else {
                    try {
                        $resolvedParameter = $this->resolve($parameterClassName);
                    } catch (ResolutionException $ex) {
                        // Check for a default value
                        if ($parameter->isDefaultValueAvailable()) {
                            $resolvedParameter = $parameter->getDefaultValue();
                        } elseif ($parameter->allowsNull()) {
                            $resolvedParameter = null;
                        } else {
                            throw $ex;
                        }
                    }
                }
            }

            $resolvedParameters[] = $resolvedParameter;
        }

        return $resolvedParameters;
    }

    /**
     * Resolves a primitive parameter
     *
     * @param ReflectionParameter $parameter The primitive parameter to resolve
     * @param array $primitives The list of primitive values
     * @return mixed The resolved primitive
     * @throws DependencyInjectionException Thrown if there was a problem resolving the primitive
     */
    protected function resolvePrimitive(ReflectionParameter $parameter, array &$primitives)
    {
        if (count($primitives) > 0) {
            // Grab the next primitive
            return array_shift($primitives);
        }

        if ($parameter->isDefaultValueAvailable()) {
            // No value was found, so use the default value
            return $parameter->getDefaultValue();
        }

        throw new DependencyInjectionException(
            sprintf(
                'No default value available for %s in %s::%s()',
                $parameter->getName(),
                $parameter->getDeclaringClass()->getName(),
                $parameter->getDeclaringFunction()->getName()
            )
        );
    }
}