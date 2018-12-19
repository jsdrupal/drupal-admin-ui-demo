<?php

namespace Drupal\Tests\jsonapi\Kernel\Normalizer;

use Drupal\jsonapi\Normalizer\EntityConditionNormalizer;
use Drupal\jsonapi\Query\EntityCondition;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\EntityConditionNormalizer
 * @group jsonapi
 * @group jsonapi_normalizers
 * @group legacy
 *
 * @internal
 */
class EntityConditionNormalizerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'system',
    'jsonapi',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->normalizer = $this->container->get('serializer.normalizer.entity_condition.jsonapi');
  }

  /**
   * @covers ::denormalize
   * @dataProvider denormalizeProvider
   */
  public function testDenormalize($case) {
    $normalized = $this->normalizer->denormalize($case, EntityCondition::class);
    $this->assertEquals($case['path'], $normalized->field());
    $this->assertEquals($case['value'], $normalized->value());
    if (isset($case['operator'])) {
      $this->assertEquals($case['operator'], $normalized->operator());
    }
  }

  /**
   * Data provider for testDenormalize.
   */
  public function denormalizeProvider() {
    return [
      [['path' => 'some_field', 'value' => NULL, 'operator' => '=']],
      [['path' => 'some_field', 'operator' => '=', 'value' => 'some_string']],
      [['path' => 'some_field', 'operator' => '<>', 'value' => 'some_string']],
      [
        [
          'path' => 'some_field',
          'operator' => 'NOT BETWEEN',
          'value' => 'some_string',
        ],
      ],
      [
        [
          'path' => 'some_field',
          'operator' => 'BETWEEN',
          'value' => ['some_string'],
        ],
      ],
    ];
  }

  /**
   * @covers ::denormalize
   * @dataProvider denormalizeValidationProvider
   */
  public function testDenormalizeValidation($input, $exception) {
    if ($exception) {
      $this->setExpectedException(get_class($exception), $exception->getMessage());
    }
    $this->normalizer->denormalize($input, EntityCondition::class);
  }

  /**
   * Data provider for denormalizeProvider.
   */
  public function denormalizeValidationProvider() {
    return [
      [['path' => 'some_field', 'value' => 'some_value'], NULL],
      [
        ['path' => 'some_field', 'value' => 'some_value', 'operator' => '='],
        NULL,
      ],
      [['path' => 'some_field', 'operator' => 'IS NULL'], NULL],
      [['path' => 'some_field', 'operator' => 'IS NOT NULL'], NULL],
      [
        ['path' => 'some_field', 'operator' => 'IS', 'value' => 'some_value'],
        new BadRequestHttpException("The 'IS' operator is not allowed in a filter parameter."),
      ],
      [
        [
          'path' => 'some_field',
          'operator' => 'NOT_ALLOWED',
          'value' => 'some_value',
        ],
        new BadRequestHttpException("The 'NOT_ALLOWED' operator is not allowed in a filter parameter."),
      ],
      [
        [
          'path' => 'some_field',
          'operator' => 'IS NULL',
          'value' => 'should_not_be_here',
        ],
        new BadRequestHttpException("Filters using the 'IS NULL' operator should not provide a value."),
      ],
      [
        [
          'path' => 'some_field',
          'operator' => 'IS NOT NULL',
          'value' => 'should_not_be_here',
        ],
        new BadRequestHttpException("Filters using the 'IS NOT NULL' operator should not provide a value."),
      ],
      [
        ['path' => 'path_only'],
        new BadRequestHttpException("Filter parameter is missing a '" . EntityConditionNormalizer::VALUE_KEY . "' key."),
      ],
      [
        ['value' => 'value_only'],
        new BadRequestHttpException("Filter parameter is missing a '" . EntityConditionNormalizer::PATH_KEY . "' key."),
      ],
    ];
  }

}
