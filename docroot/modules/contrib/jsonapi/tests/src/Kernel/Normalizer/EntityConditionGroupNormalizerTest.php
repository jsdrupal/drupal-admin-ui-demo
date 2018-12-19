<?php

namespace Drupal\Tests\jsonapi\Kernel\Normalizer;

use Drupal\KernelTests\KernelTestBase;
use Drupal\jsonapi\Query\EntityConditionGroup;

/**
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\EntityConditionGroupNormalizer
 * @group jsonapi
 * @group jsonapi_normalizers
 * @group legacy
 *
 * @internal
 */
class EntityConditionGroupNormalizerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'system',
    'jsonapi',
  ];

  /**
   * @covers ::denormalize
   * @dataProvider denormalizeProvider
   */
  public function testDenormalize($case) {
    $normalizer = $this->container->get('serializer.normalizer.entity_condition_group.jsonapi');

    $normalized = $normalizer->denormalize($case, EntityConditionGroup::class);

    $this->assertEquals($case['conjunction'], $normalized->conjunction());

    foreach ($normalized->members() as $key => $condition) {
      $this->assertEquals($case['members'][$key]['path'], $condition->field());
      $this->assertEquals($case['members'][$key]['value'], $condition->value());
    }
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalizeException() {
    $normalizer = $this->container->get('serializer.normalizer.entity_condition_group.jsonapi');
    $data = ['conjunction' => 'NOT_ALLOWED', 'members' => []];
    $this->setExpectedException(\InvalidArgumentException::class);
    $normalized = $normalizer->denormalize($data, EntityConditionGroup::class);
  }

  /**
   * Data provider for testDenormalize.
   */
  public function denormalizeProvider() {
    return [
      [['conjunction' => 'AND', 'members' => []]],
      [['conjunction' => 'OR', 'members' => []]],
    ];
  }

}
