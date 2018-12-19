<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer\Value;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\RelationshipItemNormalizerValue;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue
 * @group jsonapi
 *
 * @internal
 */
class FieldNormalizerValueTest extends UnitTestCase {

  /**
   * The cache contexts manager.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $cacheContextsManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->cacheContextsManager = $this->getMockBuilder('Drupal\Core\Cache\Context\CacheContextsManager')
      ->disableOriginalConstructor()
      ->getMock();
    $this->cacheContextsManager->method('assertValidTokens')->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $this->cacheContextsManager);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::rasterizeValue
   * @covers ::__construct
   * @dataProvider rasterizeValueProvider
   */
  public function testRasterizeValue($values, $cardinality, $expected) {
    $object = new FieldNormalizerValue(AccessResult::allowed()->cachePerUser()->addCacheTags(['field:foo']), $values, $cardinality, 'attributes');
    $this->assertEquals($expected, $object->rasterizeValue());
    $this->assertSame(['ccfoo', 'user'], $object->getCacheContexts());
    $this->assertSame(['ctfoo', 'field:foo'], $object->getCacheTags());
    $this->assertSame(15, $object->getCacheMaxAge());
  }

  /**
   * Data provider for testRasterizeValue.
   */
  public function rasterizeValueProvider() {
    $uuid_raw = '4ae99eec-8b0e-41f7-9400-fbd65c174902';
    $uuid_value = $this->prophesize(FieldItemNormalizerValue::class);
    $uuid_value->rasterizeValue()->willReturn('4ae99eec-8b0e-41f7-9400-fbd65c174902');
    $uuid_value->getCacheContexts()->willReturn(['ccfoo']);
    $uuid_value->getCacheTags()->willReturn(['ctfoo']);
    $uuid_value->getCacheMaxAge()->willReturn(15);
    return [
      [[$uuid_value->reveal()], 1, $uuid_raw],
      [
        [
          $uuid_value->reveal(),
          $uuid_value->reveal(),
        ],
        -1,
        [$uuid_raw, $uuid_raw],
      ],
    ];
  }

  /**
   * @covers ::rasterizeIncludes
   */
  public function testRasterizeIncludes() {
    $value = $this->prophesize(RelationshipItemNormalizerValue::class);
    $include = $this->prophesize('\Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue');
    $include->rasterizeValue()->willReturn('Lorem');
    $value->getCacheContexts()->willReturn(['ccfoo']);
    $value->getCacheTags()->willReturn(['ctfoo']);
    $value->getCacheMaxAge()->willReturn(15);
    $value->getInclude()->willReturn($include->reveal());
    $object = new FieldNormalizerValue(AccessResult::allowed(), [$value->reveal()], 1, 'attributes');
    $this->assertEquals(['Lorem'], $object->rasterizeIncludes());
  }

}
