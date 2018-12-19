<?php

namespace Drupal\Tests\jsonapi\Kernel\Normalizer;

use Drupal\KernelTests\KernelTestBase;
use Drupal\jsonapi\Query\Filter;
use Drupal\jsonapi\Context\FieldResolver;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\FilterNormalizer
 * @group jsonapi
 * @group jsonapi_normalizers
 * @group legacy
 *
 * @internal
 */
class FilterNormalizerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'system',
    'jsonapi',
  ];

  /**
   * The filter denormalizer.
   *
   * @var \Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->container->set('jsonapi.field_resolver', $this->getFieldResolver('foo', 'bar'));
    $this->normalizer = $this->container->get('serializer.normalizer.filter.jsonapi');
  }

  /**
   * @covers ::denormalize
   * @dataProvider denormalizeProvider
   */
  public function testDenormalize($normalized, $expected) {
    $actual = $this->normalizer->denormalize($normalized, Filter::class, NULL, ['entity_type_id' => 'foo', 'bundle' => 'bar']);
    $conditions = $actual->root()->members();
    for ($i = 0; $i < count($normalized); $i++) {
      $this->assertEquals($expected[$i]['path'], $conditions[$i]->field());
      $this->assertEquals($expected[$i]['value'], $conditions[$i]->value());
      $this->assertEquals($expected[$i]['operator'], $conditions[$i]->operator());
    }
  }

  /**
   * Data provider for testDenormalize.
   */
  public function denormalizeProvider() {
    return [
      'shorthand' => [
        ['uid' => ['value' => 1]],
        [['path' => 'uid', 'value' => 1, 'operator' => '=']],
      ],
      'extreme shorthand' => [
        ['uid' => 1],
        [['path' => 'uid', 'value' => 1, 'operator' => '=']],
      ],
    ];
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalizeNested() {
    $normalized = [
      'or-group' => ['group' => ['conjunction' => 'OR']],
      'nested-or-group' => [
        'group' => ['conjunction' => 'OR', 'memberOf' => 'or-group'],
      ],
      'nested-and-group' => [
        'group' => ['conjunction' => 'AND', 'memberOf' => 'or-group'],
      ],
      'condition-0' => [
        'condition' => [
          'path' => 'field0',
          'value' => 'value0',
          'memberOf' => 'nested-or-group',
        ],
      ],
      'condition-1' => [
        'condition' => [
          'path' => 'field1',
          'value' => 'value1',
          'memberOf' => 'nested-or-group',
        ],
      ],
      'condition-2' => [
        'condition' => [
          'path' => 'field2',
          'value' => 'value2',
          'memberOf' => 'nested-and-group',
        ],
      ],
      'condition-3' => [
        'condition' => [
          'path' => 'field3',
          'value' => 'value3',
          'memberOf' => 'nested-and-group',
        ],
      ],
    ];
    $filter = $this->normalizer->denormalize($normalized, Filter::class, NULL, ['entity_type_id' => 'foo', 'bundle' => 'bar']);
    $root = $filter->root();

    // Make sure the implicit root group was added.
    $this->assertEquals($root->conjunction(), 'AND');

    // Ensure the or-group and the and-group were added correctly.
    $members = $root->members();

    // Ensure the OR group was added.
    $or_group = $members[0];
    $this->assertEquals($or_group->conjunction(), 'OR');
    $or_group_members = $or_group->members();

    // Make sure the nested OR group was added with the right conditions.
    $nested_or_group = $or_group_members[0];
    $this->assertEquals($nested_or_group->conjunction(), 'OR');
    $nested_or_group_members = $nested_or_group->members();
    $this->assertEquals($nested_or_group_members[0]->field(), 'field0');
    $this->assertEquals($nested_or_group_members[1]->field(), 'field1');

    // Make sure the nested AND group was added with the right conditions.
    $nested_and_group = $or_group_members[1];
    $this->assertEquals($nested_and_group->conjunction(), 'AND');
    $nested_and_group_members = $nested_and_group->members();
    $this->assertEquals($nested_and_group_members[0]->field(), 'field2');
    $this->assertEquals($nested_and_group_members[1]->field(), 'field3');
  }

  /**
   * Provides a mock field resolver.
   */
  protected function getFieldResolver($entity_type_id, $bundle) {
    $field_resolver = $this->prophesize(FieldResolver::class);
    $field_resolver->resolveInternalEntityQueryPath('foo', 'bar', Argument::any())->willReturnArgument(2);
    return $field_resolver->reveal();
  }

}
