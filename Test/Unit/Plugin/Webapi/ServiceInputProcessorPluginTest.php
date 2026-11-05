<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Test\Unit\Plugin\Webapi;

use BigCartEnergy\SessionReaperPatch\Model\RequestLogger;
use BigCartEnergy\SessionReaperPatch\Plugin\Webapi\ServiceInputProcessorPlugin;
use BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture\Attachment;
use BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture\Loose;
use BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture\Note;
use BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture\NoteExtension;
use BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture\NoteExtensionInterface;
use BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture\NoteInterface;
use BigCartEnergy\SessionReaperPatch\Test\Unit\Fixture\Plain;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\Api\AttributeValue;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\Reflection\TypeProcessor;
use Magento\Framework\Webapi\CustomAttributeTypeLocatorInterface;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Framework\Webapi\ServiceTypeToEntityTypeMap;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real TypeProcessor and reflection library of the Magento installation the tests are run in.
 *
 * The assertions stay within what every PHPUnit from 6.5 (Magento 2.3.0) to 12 (Magento 2.4.9) offers.
 */
class ServiceInputProcessorPluginTest extends TestCase
{
    /**
     * Every preference request the configuration double received, in order.
     *
     * @var string[]
     */
    private $preferenceRequests = [];

    /**
     * Every [code, entity type] pair the type locator double received, in order.
     *
     * @var array[]
     */
    private $locatorRequests = [];

    protected function setUp(): void
    {
        if (!class_exists(TypeProcessor::class) || !class_exists(SearchCriteria::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
        $this->preferenceRequests = [];
        $this->locatorRequests = [];
    }

    public function testAKeyNamingAConstructorParameterOutsideApiDataIsRemovedAndTheRestStays(): void
    {
        $data = [
            'title' => 'Hello',
            'attachment' => ['label' => 'paper'],
            'tag' => ['name' => 'urgent'],
            'unrelated' => 'stays, it names no constructor parameter',
        ];
        $result = $this->convert($data, Note::class);

        self::assertNotNull($result);
        self::assertSame(Note::class, $result[1], 'the type is returned unchanged');
        self::assertArrayNotHasKey('attachment', $result[0], 'the parameter outside Api\Data is removed');
        self::assertSame('Hello', $result[0]['title'], 'the simple-typed parameter stays');
        self::assertSame(['name' => 'urgent'], $result[0]['tag'], 'the Api\Data-typed parameter stays');
        self::assertSame($data['unrelated'], $result[0]['unrelated'], 'a key naming no parameter stays');
    }

    public function testNothingIsRemovedWhenEveryKeyIsOnTheAllowList(): void
    {
        self::assertNull($this->convert(['title' => 'Hello', 'tag' => ['name' => 'urgent']], Note::class));
        self::assertNull($this->convert(['title' => 'Hello', 'unrelated' => ['x' => 'y']], Note::class));
    }

    public function testTheDataKeyIsRemovedAsTheOfficialFixSkipsIt(): void
    {
        $result = $this->convert(['title' => 'Hello', 'data' => ['title' => 'other']], Note::class);
        self::assertNotNull($result);
        self::assertSame(['title' => 'Hello'], $result[0]);
    }

    public function testAParameterDocumentedAsMixedIsNotOnTheAllowList(): void
    {
        $result = $this->convert(['thing' => ['any' => 'thing']], Loose::class);
        self::assertNotNull($result);
        self::assertSame([], $result[0]);
    }

    public function testAnArrayOfComplexTypesIsSanitizedItemByItem(): void
    {
        $data = [
            ['title' => 'One', 'attachment' => ['label' => 'a']],
            ['title' => 'Two'],
            'named' => ['title' => 'Three', 'attachment' => ['label' => 'c'], 'tag' => ['name' => 't']],
        ];
        $result = $this->convert($data, Note::class . '[]');

        self::assertNotNull($result);
        self::assertSame(Note::class . '[]', $result[1]);
        self::assertSame(['title' => 'One'], $result[0][0]);
        self::assertSame(['title' => 'Two'], $result[0][1]);
        self::assertSame(['title' => 'Three', 'tag' => ['name' => 't']], $result[0]['named']);
    }

    public function testTheSoapItemWrapperIsLeftToTheProcessorAndItsItemsAreSanitized(): void
    {
        $list = ['item' => [
            ['title' => 'One', 'attachment' => ['label' => 'a']],
            ['title' => 'Two', 'attachment' => ['label' => 'b']],
        ]];
        $result = $this->convert($list, Note::class . '[]');
        self::assertNotNull($result);
        self::assertSame(['item' => [['title' => 'One'], ['title' => 'Two']]], $result[0]);

        $single = ['item' => ['title' => 'One', 'attachment' => ['label' => 'a']]];
        $result = $this->convert($single, Note::class . '[]');
        self::assertNotNull($result);
        self::assertSame(['item' => ['title' => 'One']], $result[0]);

        self::assertNull($this->convert(['item' => 'scalar'], Note::class . '[]'));
        self::assertNull($this->convert(['item' => [['title' => 'One'], ['title' => 'Two']]], Note::class . '[]'));
    }

    public function testCustomAttributesExtensionAttributesAndSearchCriteriaAreUntouched(): void
    {
        $customAttributes = [
            ['attribute_code' => 'color', 'value' => 'red'],
            ['attribute_code' => 'sizes', 'value' => ['s', 'm']],
        ];
        self::assertNull($this->convert($customAttributes, AttributeInterface::class . '[]', [
            AttributeInterface::class => AttributeValue::class,
        ]));

        self::assertNull($this->convert(['remark' => 'a remark'], NoteExtensionInterface::class));
        self::assertContains(
            NoteExtension::class,
            $this->preferenceRequests,
            'the extension attributes interface is resolved through its generated class, as the processor does'
        );

        $searchCriteria = [
            'filter_groups' => [['filters' => [['field' => 'sku', 'value' => 'A%', 'condition_type' => 'like']]]],
            'sort_orders' => [['field' => 'name', 'direction' => 'ASC']],
            'page_size' => 20,
            'current_page' => 1,
        ];
        self::assertNull($this->convert($searchCriteria, SearchCriteriaInterface::class, [
            SearchCriteriaInterface::class => SearchCriteria::class,
        ]));
    }

    public function testAClassWithoutAConstructorIsLeftAlone(): void
    {
        self::assertNull($this->convert(['label' => 'x', 'anything' => ['y']], Plain::class));
    }

    public function testThePreferenceOfAnInterfaceIsTheClassReflected(): void
    {
        $result = $this->convert(
            ['title' => 'Hello', 'attachment' => ['label' => 'paper']],
            NoteInterface::class,
            [NoteInterface::class => Note::class]
        );
        self::assertNotNull($result);
        self::assertSame(['title' => 'Hello'], $result[0]);
        self::assertSame(NoteInterface::class, $result[1]);
    }

    public function testTheNoChangePathReturnsNull(): void
    {
        self::assertNull($this->convert('scalar', Note::class), 'non-array data');
        self::assertNull($this->convert(null, Note::class), 'null data');
        self::assertNull($this->convert([], Note::class), 'empty data');
        self::assertNull($this->convert(['attachment' => ['label' => 'a']], 'string'), 'simple type');
        self::assertNull($this->convert(['attachment' => ['label' => 'a']], 'int[]'), 'array of simple type');
        self::assertNull($this->convert(['attachment' => ['label' => 'a']], 'mixed'), 'any type');
        self::assertNull(
            $this->convert(['attachment' => ['label' => 'a']], 'NoSuchClassForTheGuardTest'),
            'unknown class'
        );
        self::assertNull($this->convert(['attachment' => ['label' => 'a']], ''), 'empty type');
        self::assertNull($this->convert(['attachment' => ['label' => 'a']], null), 'non-string type');
        if (defined(TypeProcessor::class . '::UNSTRUCTURED_ARRAY')) {
            $unstructured = constant(TypeProcessor::class . '::UNSTRUCTURED_ARRAY');
            self::assertNull(
                $this->convert(['attachment' => ['label' => 'a']], $unstructured),
                'the unstructured array pass-through'
            );
        }
    }

    public function testAKeyWithANullValueIsNotConstructorInputAndStays(): void
    {
        self::assertNull($this->convert(['title' => 'Hello', 'attachment' => null], Note::class));
    }

    public function testTheRemovedKeysAreLoggedWithTheirTypesUnlessLoggingIsOff(): void
    {
        $logger = $this->createMock(RequestLogger::class);
        $logger->expects(self::once())
            ->method('logStripped')
            ->with(
                Note::class . '[]',
                self::callback(function ($parameters): bool {
                    return array_keys($parameters) === ['attachment', 'data']
                        && ltrim((string)$parameters['attachment'], '\\') === Attachment::class
                        && $parameters['data'] !== '';
                })
            );
        $plugin = $this->plugin($logger, true);
        $data = [
            ['title' => 'One', 'attachment' => ['label' => 'a']],
            ['title' => 'Two', 'attachment' => ['label' => 'b'], 'data' => ['x' => 1]],
        ];
        $result = $plugin->beforeConvertValue($this->subject(), $data, Note::class . '[]');
        self::assertSame([['title' => 'One'], ['title' => 'Two']], $result[0]);

        $silent = $this->createMock(RequestLogger::class);
        $silent->expects(self::never())->method('logStripped');
        $plugin = $this->plugin($silent, false);
        $result = $plugin->beforeConvertValue($this->subject(), $data, Note::class . '[]');
        self::assertSame([['title' => 'One'], ['title' => 'Two']], $result[0], 'the removal stands without logging');
    }

    public function testAComplexCustomAttributeValueGetsTheAllowListToo(): void
    {
        $data = [
            'title' => 'Hello',
            'custom_attributes' => [
                [
                    'attribute_code' => 'memo',
                    'value' => ['title' => 'Inner', 'attachment' => ['label' => 'a'], 'tag' => ['name' => 't']],
                ],
                ['attribute_code' => 'color', 'value' => 'red'],
            ],
        ];
        $result = $this->convert(
            $data,
            Note::class,
            [],
            [Note::class => 'bench_note'],
            ['memo' => Note::class, 'color' => 'string']
        );

        self::assertNotNull($result);
        self::assertSame('Hello', $result[0]['title']);
        self::assertSame(
            ['title' => 'Inner', 'tag' => ['name' => 't']],
            $result[0]['custom_attributes'][0]['value'],
            'the parameter outside Api\Data is removed from the custom attribute value, the Api\Data one stays'
        );
        self::assertSame('red', $result[0]['custom_attributes'][1]['value'], 'a scalar value is left alone');
        self::assertSame([['memo', 'bench_note']], $this->locatorRequests, 'only the array value is resolved');
    }

    public function testSimpleUnknownAndScalarCustomAttributesAreLeftAlone(): void
    {
        $data = [
            'title' => 'Hello',
            'custom_attributes' => [
                ['attribute_code' => 'sizes', 'value' => ['s', 'm']],
                ['attribute_code' => 'unknown_code', 'value' => ['title' => 'x', 'attachment' => ['label' => 'a']]],
                ['attribute_code' => 'memo', 'value' => 'a string, not an object'],
                ['attribute_code' => 'anything', 'value' => ['attachment' => ['label' => 'a']]],
                ['value' => ['attachment' => ['label' => 'a']]],
                ['attribute_code' => 'memo'],
                'plain scalar entry',
            ],
        ];
        $result = $this->convert(
            $data,
            Note::class,
            [],
            [Note::class => 'bench_note'],
            ['sizes' => 'string[]', 'memo' => Note::class, 'anything' => 'mixed']
        );
        self::assertNull($result, 'a simple type, an unknown code, a scalar value, "mixed" and rejected entries');
    }

    public function testAnArrayTypedCustomAttributeIsSanitizedItemByItem(): void
    {
        $data = [
            'custom_attributes' => [
                [
                    'attribute_code' => 'memos',
                    'value' => [
                        ['title' => 'One', 'attachment' => ['label' => 'a']],
                        ['title' => 'Two', 'tag' => ['name' => 't']],
                        'scalar item left alone',
                    ],
                ],
            ],
        ];
        $types = ['memos' => Note::class . '[]'];
        $result = $this->convert($data, Note::class, [], [Note::class => 'bench_note'], $types);

        self::assertNotNull($result);
        self::assertSame(
            [['title' => 'One'], ['title' => 'Two', 'tag' => ['name' => 't']], 'scalar item left alone'],
            $result[0]['custom_attributes'][0]['value']
        );
    }

    public function testTheSoapShapeOfCustomAttributesIsHandledToo(): void
    {
        $data = [
            'title' => 'Hello',
            'customAttributes' => [
                ['attributeCode' => 'memo', 'value' => ['title' => 'Inner', 'attachment' => ['label' => 'a']]],
            ],
        ];
        $result = $this->convert($data, Note::class, [], [Note::class => 'bench_note'], ['memo' => Note::class]);

        self::assertNotNull($result);
        self::assertSame(['title' => 'Inner'], $result[0]['customAttributes'][0]['value']);
    }

    public function testAnOwnerWithoutAnEntityTypeNeverConsultsTheLocator(): void
    {
        $data = [
            'custom_attributes' => [
                ['attribute_code' => 'memo', 'value' => ['title' => 'Inner', 'attachment' => ['label' => 'a']]],
            ],
        ];
        self::assertNull($this->convert($data, Note::class, [], [], ['memo' => Note::class]));
        self::assertSame([], $this->locatorRequests);
    }

    public function testALocatorFailureLeavesTheValueToTheProcessor(): void
    {
        $data = [
            'custom_attributes' => [
                ['attribute_code' => 'memo', 'value' => ['title' => 'Inner', 'attachment' => ['label' => 'a']]],
            ],
        ];
        $result = $this->plugin($this->stub(RequestLogger::class), true, [], [Note::class => 'bench_note'], null)
            ->beforeConvertValue($this->subject(), $data, Note::class);
        self::assertNull($result);
    }

    public function testCustomAttributeRemovalsAreLoggedUnderTheAttributePath(): void
    {
        $logger = $this->createMock(RequestLogger::class);
        $logger->expects(self::once())
            ->method('logStripped')
            ->with(
                Note::class,
                self::callback(function ($parameters): bool {
                    $path = 'custom_attributes[memo].attachment';
                    return array_keys($parameters) === [$path]
                        && ltrim((string)$parameters[$path], '\\') === Attachment::class;
                })
            );
        $data = [
            'title' => 'Hello',
            'custom_attributes' => [
                ['attribute_code' => 'memo', 'value' => ['title' => 'Inner', 'attachment' => ['label' => 'a']]],
            ],
        ];
        $result = $this->plugin($logger, true, [], [Note::class => 'bench_note'], ['memo' => Note::class])
            ->beforeConvertValue($this->subject(), $data, Note::class);
        self::assertSame(['title' => 'Inner'], $result[0]['custom_attributes'][0]['value']);
    }

    /**
     * @param mixed $data
     * @param mixed $type
     * @param string[] $preferences
     * @param string[] $entityTypes Class to entity type, as the processor's map
     * @param string[] $attributeTypes Attribute code to type, as the processor's locator
     * @return array|null
     */
    private function convert(
        $data,
        $type,
        array $preferences = [],
        array $entityTypes = [],
        array $attributeTypes = []
    ): ?array {
        return $this->plugin($this->stub(RequestLogger::class), true, $preferences, $entityTypes, $attributeTypes)
            ->beforeConvertValue($this->subject(), $data, $type);
    }

    /**
     * @param RequestLogger $logger
     * @param bool $enableLog
     * @param string[] $preferences
     * @param string[] $entityTypes
     * @param string[]|null $attributeTypes Null for a locator that fails
     * @return ServiceInputProcessorPlugin
     */
    private function plugin(
        RequestLogger $logger,
        bool $enableLog,
        array $preferences = [],
        array $entityTypes = [],
        ?array $attributeTypes = []
    ): ServiceInputProcessorPlugin {
        $config = $this->stub(ConfigInterface::class);
        $config->method('getPreference')->willReturnCallback(function ($type) use ($preferences): string {
            $this->preferenceRequests[] = (string)$type;
            return $preferences[$type] ?? (string)$type;
        });
        $locator = $this->stub(CustomAttributeTypeLocatorInterface::class);
        $locator->method('getType')->willReturnCallback(function ($code, $entityType) use ($attributeTypes): string {
            $this->locatorRequests[] = [(string)$code, (string)$entityType];
            if ($attributeTypes === null) {
                throw new \RuntimeException('locator failure');
            }
            return $attributeTypes[$code] ?? TypeProcessor::NORMALIZED_ANY_TYPE;
        });
        return new ServiceInputProcessorPlugin(
            new TypeProcessor(),
            $config,
            $logger,
            $locator,
            new ServiceTypeToEntityTypeMap($entityTypes),
            $enableLog
        );
    }

    private function subject(): ServiceInputProcessor
    {
        return $this->stub(ServiceInputProcessor::class);
    }

    /**
     * A test double without expectations: a stub where the runner offers one, a mock on older runners.
     *
     * @param string $class
     * @return \PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\Stub
     */
    private function stub(string $class)
    {
        return method_exists($this, 'createStub') ? $this->createStub($class) : $this->createMock($class);
    }
}
