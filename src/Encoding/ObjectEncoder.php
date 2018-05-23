<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2018 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Serialization\Encoding;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Defines an object encoder
 */
class ObjectEncoder implements IEncoder
{
    /** @var EncoderRegistry The encoder registry */
    private $encoders;
    /** @var IPropertyNameFormatter|null The property name formatter to use */
    private $propertyNameFormatter;
    /** @var array The mapping of types to encoded property names to ignore */
    private $ignoredEncodedPropertyNamesByType = [];

    /**
     * @param EncoderRegistry $encoders The encoder registry
     * @param IPropertyNameFormatter|null $propertyNameFormatter The property name formatter to use
     */
    public function __construct(EncoderRegistry $encoders, IPropertyNameFormatter $propertyNameFormatter = null)
    {
        $this->encoders = $encoders;
        $this->propertyNameFormatter = $propertyNameFormatter;
    }

    /**
     * Adds a property to ignore during encoding
     *
     * @param string $type The type whose property we're ignoring
     * @param string $propertyName The name of the property to ignore
     */
    public function addIgnoredProperty(string $type, string $propertyName): void
    {
        if (!isset($this->ignoredEncodedPropertyNamesByType[$type])) {
            $this->ignoredEncodedPropertyNamesByType[$type] = [];
        }

        $this->ignoredEncodedPropertyNamesByType[$type][$this->normalizePropertyName($propertyName)] = true;
    }

    /**
     * @inheritdoc
     */
    public function decode($objectHash, string $type)
    {
        if (!\class_exists($type)) {
            throw new InvalidArgumentException("Type $type is not a valid class name");
        }

        if (!\is_array($objectHash)) {
            throw new InvalidArgumentException('Value must be an associative array');
        }

        $reflectionClass = new ReflectionClass($type);
        $normalizedPropertyNames = $this->normalizeHashProperties($objectHash);
        $unusedNormalizedPropertyNames = $normalizedPropertyNames;
        $constructorParams = [];
        $constructor = $reflectionClass->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $constructorParam) {
                $encodedConstructorParamName = $this->normalizePropertyName($constructorParam->getName());

                if (isset($normalizedPropertyNames[$encodedConstructorParamName])) {
                    $constructorParamValue = $objectHash[$normalizedPropertyNames[$encodedConstructorParamName]];
                    $decodedConstructorParamValue = $this->decodeConstructorParamValue(
                        $constructorParam,
                        $constructorParamValue,
                        $reflectionClass,
                        $encodedConstructorParamName
                    );

                    if ($constructorParam->isVariadic()) {
                        $constructorParams = array_merge($constructorParams, $decodedConstructorParamValue);
                    } else {
                        $constructorParams[] = $decodedConstructorParamValue;
                    }

                    unset($unusedNormalizedPropertyNames[$encodedConstructorParamName]);
                } elseif ($constructorParam->isDefaultValueAvailable()) {
                    $constructorParams[] = $constructorParam->getDefaultValue();
                } elseif ($constructorParam->allowsNull()) {
                    // The property wasn't in the hash, but the parameter is nullable
                    $constructorParams[] = null;
                } else {
                    throw new EncodingException("No value specified for parameter \"{$constructorParam->getName()}\"");
                }
            }
        }

        $object = $reflectionClass->newInstanceArgs($constructorParams);

        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $publicProperty) {
            // We don't want to overwrite any already-set public properties
            if ($publicProperty->getValue($object) !== null) {
                continue;
            }

            $encodedPropertyName = $this->normalizePropertyName($publicProperty->getName());

            if (isset($unusedNormalizedPropertyNames[$encodedPropertyName])) {
                // Since public properties aren't typed, we cannot decode it automatically.  So, just use the raw value.
                $propertyValue = $objectHash[$normalizedPropertyNames[$encodedPropertyName]];
                $object->{$publicProperty->getName()} = $propertyValue;
            }

            unset($unusedNormalizedPropertyNames[$encodedPropertyName]);
        }

        return $object;
    }

    /**
     * @inheritdoc
     */
    public function encode($object)
    {
        if (!\is_object($object)) {
            throw new InvalidArgumentException('Value must be an object');
        }

        $encodedObject = [];
        $reflectionObject = new ReflectionObject($object);

        foreach ($reflectionObject->getProperties() as $property) {
            if ($this->propertyIsIgnored($reflectionObject->getName(), $property->getName())) {
                continue;
            }

            if (!$property->isPublic()) {
                $property->setAccessible(true);
            }

            $formattedPropertyName = $this->propertyNameFormatter === null ?
                $property->getName() :
                $this->propertyNameFormatter->formatPropertyName($property->getName());
            $propertyValue = $property->getValue($object);
            $encodedObject[$formattedPropertyName] = $this->encoders->getEncoderForValue($propertyValue)
                ->encode($propertyValue);
        }

        return $encodedObject;
    }

    /**
     * Decodes a variadic contructor parameter value
     *
     * @param ReflectionParameter $constructorParam The constructor parameter to decode
     * @param mixed $constructorParamValue The encoded constructor parameter value
     * @return mixed The decoded value
     * @throws EncodingException Thrown if the value was not an array
     */
    protected function decodeArrayOrVariadicConstructorParamValue(
        ReflectionParameter $constructorParam,
        $constructorParamValue
    ) {
        if (!\is_array($constructorParamValue)) {
            throw new EncodingException('Value must be an array');
        }

        if (\count($constructorParamValue) === 0) {
            return [];
        }

        if ($constructorParam->isVariadic() && $constructorParam->hasType()) {
            $type = $constructorParam->getType() . '[]';

            return $this->encoders->getEncoderForType($type)
                ->decode($constructorParamValue, $type);
        }

        if (\is_object($constructorParamValue[0])) {
            $type = \get_class($constructorParamValue[0]) . '[]';

            return $this->encoders->getEncoderForType($type)
                ->decode($constructorParamValue, $type);
        }

        $type = gettype($constructorParamValue[0]) . '[]';

        return $this->encoders->getEncoderForType($type)
            ->decode($constructorParamValue, $type);
    }

    /**
     * Decodes a constructor parameter value
     *
     * @param ReflectionParameter $constructorParam The constructor parameter to decode
     * @param mixed $constructorParamValue The encoded constructor parameter value
     * @param ReflectionClass $reflectionClass The reflection class we're trying to instantiate
     * @param string $normalizedHashPropertyName The encoded property name from the hash
     * @return mixed The decoded constructor parameter value
     * @throws EncodingException Thrown if the value could not be automatically decoded
     */
    protected function decodeConstructorParamValue(
        ReflectionParameter $constructorParam,
        $constructorParamValue,
        ReflectionClass $reflectionClass,
        string $normalizedHashPropertyName
    ) {
        if ($constructorParam->hasType() && !$constructorParam->isArray() && !$constructorParam->isVariadic()) {
            return $this->encoders->getEncoderForType($constructorParam->getType())
                ->decode($constructorParamValue, $constructorParam->getType());
        }

        if ($constructorParam->isVariadic() || $constructorParam->isArray()) {
            return $this->decodeArrayOrVariadicConstructorParamValue($constructorParam, $constructorParamValue);
        }

        $decodedValue = null;

        if (
            $this->tryDecodeValueFromGetterType(
                $reflectionClass,
                $normalizedHashPropertyName,
                $constructorParamValue,
                $decodedValue
            )
        ) {
            return $decodedValue;
        }

        // At this point, let's just check if the value we're trying to decode is a scalar, and if so, just return it
        if (\is_scalar($constructorParamValue)) {
            $type = \gettype($constructorParamValue);

            return $this->encoders->getEncoderForType($type)
                ->decode($constructorParamValue, $type);
        }

        throw new EncodingException("Failed to decode constructor parameter {$constructorParam->getName()}");
    }

    /**
     * Gets the normalized hash property names to original names
     *
     * @param array $objectHash The object hash whose properties we're normalizing
     * @return array The mapping of normalized names to original names
     */
    protected function normalizeHashProperties(array $objectHash): array
    {
        $encodedHashProperties = [];

        foreach ($objectHash as $propertyName => $propertyValue) {
            $encodedHashProperties[$this->normalizePropertyName($propertyName)] = $propertyName;
        }

        return $encodedHashProperties;
    }

    /**
     * Normalizes a property name to support fuzzy matching
     *
     * @param string $propertyName The property name to normalize
     * @return string The normalized property name
     */
    protected function normalizePropertyName(string $propertyName): string
    {
        return strtolower(str_replace('_', '', $propertyName));
    }

    /**
     * Checks whether or not a property on a type is ignored
     *
     * @param string $type The type to check
     * @param string $propertyName The property name to check
     * @return bool True if the property should be ignored, otherwise false
     */
    protected function propertyIsIgnored(string $type, string $propertyName): bool
    {
        return isset($this->ignoredEncodedPropertyNamesByType[$type]) &&
            isset($this->ignoredEncodedPropertyNamesByType[$type][$this->normalizePropertyName($propertyName)]);
    }

    /**
     * Decodes a value using the type info from get, is, or has methods
     *
     * @param ReflectionClass $reflectionClass The reflection class
     * @param string $normalizedPropertyName The normalized property name
     * @param mixed $encodedValue The encoded value
     * @param mixed The decoded value
     * @return bool Returns true if the value was successfully decoded, otherwise false
     */
    protected function tryDecodeValueFromGetterType(
        ReflectionClass $reflectionClass,
        string $normalizedPropertyName,
        $encodedValue,
        &$decodedValue
    ): bool {
        // Check if we can infer the type from any getters or setters
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if (
                !$reflectionMethod->hasReturnType() ||
                $reflectionMethod->getReturnType() === 'array' ||
                $reflectionMethod->isConstructor() ||
                $reflectionMethod->isDestructor() ||
                $reflectionMethod->getNumberOfRequiredParameters() > 0
            ) {
                continue;
            }

            $propertyName = null;

            // Try to extract the property name from the getter/has-er/is-er
            if (substr($reflectionMethod->name, 0, 3) === 'get' || substr($reflectionMethod->name, 0, 3) === 'has') {
                $propertyName = lcfirst(substr($reflectionMethod->name, 3));
            } elseif (substr($reflectionMethod->name, 0, 2) === 'is') {
                $propertyName = lcfirst(substr($reflectionMethod->name, 2));
            }

            if ($propertyName === null) {
                continue;
            }

            $encodedPropertyName = $this->normalizePropertyName($propertyName);

            // This getter matches the property name we're looking for
            if ($encodedPropertyName === $normalizedPropertyName) {
                $decodedValue = $this->encoders->getEncoderForType($reflectionMethod->getReturnType())
                    ->decode($encodedValue, $reflectionMethod->getReturnType());

                return true;
            }
        }

        return false;
    }
}
