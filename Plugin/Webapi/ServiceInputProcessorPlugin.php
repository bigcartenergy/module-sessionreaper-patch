<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Plugin\Webapi;

use BigCartEnergy\SessionReaperPatch\Model\RequestLogger;
use Magento\Framework\Api\AttributeValue;
use Magento\Framework\Api\ExtensionAttributesInterface;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\Reflection\TypeProcessor;
use Magento\Framework\Webapi\CustomAttributeTypeLocatorInterface;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Framework\Webapi\ServiceTypeToEntityTypeMap;

/**
 * Guard for CVE-2025-54236 on the Web API input processor.
 *
 * Before the processor turns request data into an object, every key that names a constructor parameter of the
 * target class is kept only when that parameter's type is a simple type or an Api\Data type: the allow-list
 * Adobe added to the processor's getConstructorData() in the fixed releases, mirrored character for character.
 * Every other such key is removed from the data, so it reaches neither the constructor nor the setter fallback
 * (the official fix leaves the key in place for the setters; removing it is this module's one deliberate
 * difference). Array types are handled item by item, the SOAP "item" wrapper included.
 *
 * Custom attributes get the same rule. The processor builds an object-typed custom attribute value without
 * passing through convertValue(), so the plugin resolves each attribute's type the way the processor does (the
 * entity type of the owning class, then the custom attribute type locator) and applies the allow-list to the
 * value, or to each item of an array-typed value. Simple and "mixed" attributes, unknown codes, values that are
 * not arrays and owners without an entity type are left alone, as are entries the processor would reject
 * itself. When nothing is removed the plugin returns null and the processor receives its arguments untouched.
 */
class ServiceInputProcessorPlugin
{
    /**
     * The allow-list pattern of the official fix, character for character: a Vendor\Module\Api\Data\ type.
     */
    private const API_DATA_TYPE_PATTERN = '~\\\\?\w+\\\\\w+\\\\Api\\\\Data\\\\~';

    /**
     * The key the SOAP server wraps array values in; the processor removes it after this plugin has run.
     */
    private const SOAP_ITEM_KEY = 'item';

    /**
     * The keys under which a data object's custom attributes arrive: REST (snake case) and SOAP (camel case).
     */
    private const CUSTOM_ATTRIBUTES_KEYS = ['custom_attributes', 'customAttributes'];

    /**
     * The keys that carry a custom attribute's code: REST (snake case) and SOAP (camel case), as the processor
     * reads them.
     */
    private const ATTRIBUTE_CODE_KEYS = [AttributeValue::ATTRIBUTE_CODE, 'attributeCode'];

    /**
     * The reflection classes the Magento versions this module supports ship, in order of preference.
     */
    private const REFLECTION_CLASSES = [
        \Laminas\Code\Reflection\ClassReflection::class,
        \Zend\Code\Reflection\ClassReflection::class,
    ];

    /**
     * @var TypeProcessor
     */
    private $typeProcessor;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var RequestLogger
     */
    private $requestLogger;

    /**
     * @var CustomAttributeTypeLocatorInterface
     */
    private $customAttributeTypeLocator;

    /**
     * @var ServiceTypeToEntityTypeMap
     */
    private $serviceTypeToEntityTypeMap;

    /**
     * @var bool
     */
    private $enableLog;

    /**
     * Constructor parameters by class name, as [parameter name => parameter reflection]; [] when none.
     *
     * @var array[]
     */
    private $constructorParameters = [];

    /**
     * Allow-list decisions by class name and parameter name, as ['allowed' => bool, 'type' => string].
     *
     * @var array[]
     */
    private $decisions = [];

    /**
     * Complex custom attribute types by entity type and attribute code; false where the processor would convert
     * the value through convertValue() instead (simple or "mixed" type) or the type could not be read.
     *
     * @var array[]
     */
    private $customAttributeTypes = [];

    /**
     * @param TypeProcessor $typeProcessor
     * @param ConfigInterface $config The ObjectManager configuration, for the preference of a type
     * @param RequestLogger $requestLogger
     * @param CustomAttributeTypeLocatorInterface $customAttributeTypeLocator The processor's own type locator
     * @param ServiceTypeToEntityTypeMap $serviceTypeToEntityTypeMap The processor's own class to entity type map
     * @param bool $enableLog Whether a request with removed keys writes a line to the module's log file
     */
    public function __construct(
        TypeProcessor $typeProcessor,
        ConfigInterface $config,
        RequestLogger $requestLogger,
        CustomAttributeTypeLocatorInterface $customAttributeTypeLocator,
        ServiceTypeToEntityTypeMap $serviceTypeToEntityTypeMap,
        bool $enableLog = true
    ) {
        $this->typeProcessor = $typeProcessor;
        $this->config = $config;
        $this->requestLogger = $requestLogger;
        $this->customAttributeTypeLocator = $customAttributeTypeLocator;
        $this->serviceTypeToEntityTypeMap = $serviceTypeToEntityTypeMap;
        $this->enableLog = $enableLog;
    }

    /**
     * Removes from the data every key that names a constructor parameter not on the allow-list.
     *
     * @param ServiceInputProcessor $subject
     * @param mixed $data
     * @param string $type
     * @return array|null The sanitized [$data, $type], or null when nothing was removed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeConvertValue(ServiceInputProcessor $subject, $data, $type)
    {
        if (!is_array($data) || $data === [] || !is_string($type) || $type === '') {
            return null;
        }
        if ($this->typeProcessor->isTypeSimple($type) || $this->typeProcessor->isTypeAny($type)) {
            return null;
        }
        if ($this->isUnstructuredArray($type)) {
            return null;
        }

        $removed = [];
        if ($this->typeProcessor->isArrayType($type)) {
            $data = $this->sanitizeItems($data, (string)$this->typeProcessor->getArrayItemType($type), $removed);
        } else {
            $data = $this->sanitize($data, $type, $removed);
        }
        if ($removed === []) {
            return null;
        }
        if ($this->enableLog) {
            $this->requestLogger->logStripped($type, $removed);
        }
        return [$data, $type];
    }

    /**
     * Sanitizes every item of an array type.
     *
     * The shapes the processor accepts are a list of items, or the SOAP "item" wrapper around a list of items
     * or around a single item. The wrapper is left in place.
     *
     * @param array $data
     * @param string $className The item type
     * @param array $removed Collects [parameter name => type] of every key removed
     * @return array
     */
    private function sanitizeItems(array $data, string $className, array &$removed): array
    {
        if (isset($data[self::SOAP_ITEM_KEY])) {
            $items = $data[self::SOAP_ITEM_KEY];
            if (!is_array($items)) {
                return $data;
            }
            if (array_keys($items) !== range(0, count($items) - 1)) {
                $data[self::SOAP_ITEM_KEY] = $this->sanitize($items, $className, $removed);
                return $data;
            }
            foreach ($items as $key => $item) {
                if (is_array($item)) {
                    $data[self::SOAP_ITEM_KEY][$key] = $this->sanitize($item, $className, $removed);
                }
            }
            return $data;
        }
        foreach ($data as $key => $item) {
            if (is_array($item)) {
                $data[$key] = $this->sanitize($item, $className, $removed);
            }
        }
        return $data;
    }

    /**
     * Removes from one item every key that names a constructor parameter not on the allow-list.
     *
     * The same rule is then applied inside the item's object-typed custom attribute values. A key with a null
     * value is left alone, as the processor never treats it as constructor input.
     *
     * @param array $item
     * @param string $className
     * @param array $removed Collects [parameter name => type] of every key removed
     * @param string $label Prefix for the names collected in $removed (the custom attribute path)
     * @return array
     */
    private function sanitize(array $item, string $className, array &$removed, string $label = ''): array
    {
        foreach ($this->constructorParameters($className) as $name => $parameter) {
            if (!isset($item[$name])) {
                continue;
            }
            $decision = $this->decision($className, (string)$name, $parameter);
            if ($decision['allowed']) {
                continue;
            }
            unset($item[$name]);
            $removed[$label . $name] = $decision['type'];
        }
        return $this->sanitizeCustomAttributes($item, $className, $removed, $label);
    }

    /**
     * Applies the allow-list to the object-typed custom attribute values of one item.
     *
     * The processor converts a custom attribute whose value is an array and whose type is neither simple nor
     * "mixed" by building that type directly, without convertValue(); this is the one path the plugin's hook does
     * not see, so the value (or each item of an array-typed value) is sanitized here against that type's
     * constructor. The type is resolved exactly as the processor resolves it: the entity type of the owning
     * class, then the custom attribute type locator. Entries the processor would reject (no code, no value) and
     * values that are not arrays are left alone.
     *
     * @param array $item
     * @param string $className
     * @param array $removed
     * @param string $label
     * @return array
     */
    private function sanitizeCustomAttributes(array $item, string $className, array &$removed, string $label): array
    {
        $entityType = null;
        foreach (self::CUSTOM_ATTRIBUTES_KEYS as $key) {
            if (!isset($item[$key]) || !is_array($item[$key])) {
                continue;
            }
            if ($entityType === null) {
                $entityType = $this->entityType($className);
            }
            if ($entityType === false) {
                return $item;
            }
            foreach ($item[$key] as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $code = $this->customAttributeCode($entry);
                if ($code === null
                    || !array_key_exists(AttributeValue::VALUE, $entry)
                    || !is_array($entry[AttributeValue::VALUE])
                ) {
                    continue;
                }
                $attributeType = $this->customAttributeType($code, $entityType);
                if ($attributeType === null) {
                    continue;
                }
                $value = $entry[AttributeValue::VALUE];
                $path = $label . $key . '[' . $code . '].';
                if (substr($attributeType, -2) === '[]') {
                    $itemType = substr($attributeType, 0, -2);
                    foreach ($value as $valueIndex => $valueItem) {
                        if (is_array($valueItem)) {
                            $value[$valueIndex] = $this->sanitize($valueItem, $itemType, $removed, $path);
                        }
                    }
                } else {
                    $value = $this->sanitize($value, $attributeType, $removed, $path);
                }
                $item[$key][$index][AttributeValue::VALUE] = $value;
            }
        }
        return $item;
    }

    /**
     * The entity type the processor maps a data object class to, or false when there is none.
     *
     * Without an entity type every custom attribute is "mixed" for the processor and goes through convertValue().
     *
     * @param string $className
     * @return string|false
     */
    private function entityType(string $className)
    {
        $owner = $className;
        if (is_subclass_of($owner, ExtensionAttributesInterface::class)) {
            $owner = substr($owner, 0, -strlen('Interface'));
        }
        $owner = ltrim($owner, '\\');
        try {
            $entityType = $this->serviceTypeToEntityTypeMap->getEntityType($owner);
        } catch (\Throwable $e) {
            return false;
        }
        return is_string($entityType) && $entityType !== '' ? $entityType : false;
    }

    /**
     * The code of a custom attribute entry, read as the processor reads it; null when there is none.
     *
     * @param array $entry
     * @return string|null
     */
    private function customAttributeCode(array $entry): ?string
    {
        foreach (self::ATTRIBUTE_CODE_KEYS as $key) {
            if (isset($entry[$key]) && is_scalar($entry[$key]) && (string)$entry[$key] !== '') {
                return (string)$entry[$key];
            }
        }
        return null;
    }

    /**
     * The type of a custom attribute when the processor would build it directly, else null.
     *
     * Null for a simple or "mixed" type (converted through convertValue(), where the plugin already acts) and
     * when the locator fails (the processor raises its own error later). Cached per entity type and code.
     *
     * @param string $code
     * @param string $entityType
     * @return string|null
     */
    private function customAttributeType(string $code, string $entityType): ?string
    {
        if (!isset($this->customAttributeTypes[$entityType][$code])) {
            $resolved = false;
            try {
                $type = $this->customAttributeTypeLocator->getType($code, $entityType);
                if (is_string($type)
                    && $type !== ''
                    && !$this->typeProcessor->isTypeAny($type)
                    && !$this->typeProcessor->isTypeSimple($type)
                ) {
                    $resolved = $type;
                }
            } catch (\Throwable $e) {
                $resolved = false;
            }
            $this->customAttributeTypes[$entityType][$code] = $resolved;
        }
        $type = $this->customAttributeTypes[$entityType][$code];
        return $type === false ? null : $type;
    }

    /**
     * The constructor parameters of the class the processor would instantiate for a type, by name.
     *
     * Resolved the way the processor does: an extension attributes interface becomes its generated class, and
     * the ObjectManager preference is applied. Empty when the class cannot be reflected or has no constructor.
     * Cached per class for the life of the plugin instance.
     *
     * @param string $className
     * @return array
     */
    private function constructorParameters(string $className): array
    {
        if (!array_key_exists($className, $this->constructorParameters)) {
            $this->constructorParameters[$className] = $this->reflectConstructorParameters($className);
        }
        return $this->constructorParameters[$className];
    }

    /**
     * Reflects the constructor parameters of a type's class.
     *
     * @param string $className
     * @return array
     */
    private function reflectConstructorParameters(string $className): array
    {
        $reflectionClass = $this->reflectionClass();
        if ($reflectionClass === null) {
            return [];
        }
        try {
            if (is_subclass_of($className, ExtensionAttributesInterface::class)) {
                $className = substr($className, 0, -strlen('Interface'));
            }
            $preference = $this->config->getPreference($className);
            $class = new $reflectionClass($preference ?: $className);
            $constructor = $class->getMethod('__construct');
        } catch (\ReflectionException $e) {
            return [];
        }
        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }
        return $parameters;
    }

    /**
     * Whether a constructor parameter is on the allow-list, with the type that decided it.
     *
     * A parameter whose type cannot be read is not on the allow-list.
     *
     * @param string $className
     * @param string $name
     * @param \ReflectionParameter $parameter
     * @return array ['allowed' => bool, 'type' => string]
     */
    private function decision(string $className, string $name, \ReflectionParameter $parameter): array
    {
        if (!isset($this->decisions[$className][$name])) {
            $type = null;
            try {
                $parameterType = $this->typeProcessor->getParamType($parameter);
                if (is_string($parameterType) && $parameterType !== '') {
                    $type = $parameterType;
                }
            } catch (\Throwable $e) {
                $type = null;
            }
            $this->decisions[$className][$name] = [
                'allowed' => $type !== null && $this->isAllowed($type),
                'type' => $type !== null ? $type : '(undetermined)',
            ];
        }
        return $this->decisions[$className][$name];
    }

    /**
     * The allow-list of the official fix: a simple type, or a Vendor\Module\Api\Data\ type.
     *
     * @param string $parameterType
     * @return bool
     */
    private function isAllowed(string $parameterType): bool
    {
        return $this->typeProcessor->isTypeSimple($parameterType)
            || preg_match(self::API_DATA_TYPE_PATTERN, $parameterType) === 1;
    }

    /**
     * Whether the type is the processor's pass-through array type, which exists from Magento 2.4 on.
     *
     * @param string $type
     * @return bool
     */
    private function isUnstructuredArray(string $type): bool
    {
        $constant = TypeProcessor::class . '::UNSTRUCTURED_ARRAY';
        return defined($constant) && $type === constant($constant);
    }

    /**
     * The reflection class this installation ships: Laminas first, Zend on older releases.
     *
     * @return string|null
     */
    private function reflectionClass(): ?string
    {
        foreach (self::REFLECTION_CLASSES as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}
