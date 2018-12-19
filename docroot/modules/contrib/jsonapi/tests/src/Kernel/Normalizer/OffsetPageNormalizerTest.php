<?php

namespace Drupal\Tests\jsonapi\Kernel\Normalizer;

use Drupal\KernelTests\KernelTestBase;
use Drupal\jsonapi\Query\OffsetPage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\OffsetPageNormalizer
 * @group jsonapi
 * @group jsonapi_normalizers
 * @group legacy
 *
 * @internal
 */
class OffsetPageNormalizerTest extends KernelTestBase {

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
    $this->normalizer = $this->container->get('serializer.normalizer.offset_page.jsonapi');
  }

  /**
   * @covers ::denormalize
   * @dataProvider denormalizeProvider
   */
  public function testDenormalize($original, $expected) {
    $actual = $this->normalizer->denormalize($original, OffsetPage::class);
    $this->assertEquals($expected['offset'], $actual->getOffset());
    $this->assertEquals($expected['limit'], $actual->getSize());
  }

  /**
   * Data provider for testGet.
   */
  public function denormalizeProvider() {
    return [
      [['offset' => 12, 'limit' => 20], ['offset' => 12, 'limit' => 20]],
      [['offset' => 12, 'limit' => 60], ['offset' => 12, 'limit' => 50]],
      [['offset' => 12], ['offset' => 12, 'limit' => 50]],
      [['offset' => 0], ['offset' => 0, 'limit' => 50]],
      [[], ['offset' => 0, 'limit' => 50]],
    ];
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalizeFail() {
    $this->setExpectedException(BadRequestHttpException::class);
    $this->normalizer->denormalize('lorem', OffsetPage::class);
  }

}
