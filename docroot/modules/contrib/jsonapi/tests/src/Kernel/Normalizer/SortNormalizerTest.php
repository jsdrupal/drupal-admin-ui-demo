<?php

namespace Drupal\Tests\jsonapi\Kernel\Normalizer;

use Drupal\KernelTests\KernelTestBase;
use Drupal\jsonapi\Context\FieldResolver;
use Drupal\jsonapi\Query\Sort;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\SortNormalizer
 * @group jsonapi
 * @group jsonapi_normalizers
 * @group legacy
 *
 * @internal
 */
class SortNormalizerTest extends KernelTestBase {

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
    $this->normalizer = $this->container->get('serializer.normalizer.sort.jsonapi');
  }

  /**
   * @covers ::denormalize
   * @dataProvider denormalizeProvider
   */
  public function testDenormalize($input, $expected) {
    $sort = $this->normalizer->denormalize($input, Sort::class, NULL, ['entity_type_id' => 'foo', 'bundle' => 'bar']);
    foreach ($sort->fields() as $index => $sort_field) {
      $this->assertEquals($expected[$index]['path'], $sort_field['path']);
      $this->assertEquals($expected[$index]['direction'], $sort_field['direction']);
      $this->assertEquals($expected[$index]['langcode'], $sort_field['langcode']);
    }
  }

  /**
   * Provides a suite of shortcut sort pamaters and their expected expansions.
   */
  public function denormalizeProvider() {
    return [
      ['lorem', [['path' => 'foo', 'direction' => 'ASC', 'langcode' => NULL]]],
      ['-lorem', [['path' => 'foo', 'direction' => 'DESC', 'langcode' => NULL]]],
      ['-lorem,ipsum', [
        ['path' => 'foo', 'direction' => 'DESC', 'langcode' => NULL],
        ['path' => 'bar', 'direction' => 'ASC', 'langcode' => NULL],
      ],
      ],
      ['-lorem,-ipsum', [
        ['path' => 'foo', 'direction' => 'DESC', 'langcode' => NULL],
        ['path' => 'bar', 'direction' => 'DESC', 'langcode' => NULL],
      ],
      ],
      [[
        ['path' => 'lorem', 'langcode' => NULL],
        ['path' => 'ipsum', 'langcode' => 'ca'],
        ['path' => 'dolor', 'direction' => 'ASC', 'langcode' => 'ca'],
        ['path' => 'sit', 'direction' => 'DESC', 'langcode' => 'ca'],
      ], [
        ['path' => 'foo', 'direction' => 'ASC', 'langcode' => NULL],
        ['path' => 'bar', 'direction' => 'ASC', 'langcode' => 'ca'],
        ['path' => 'baz', 'direction' => 'ASC', 'langcode' => 'ca'],
        ['path' => 'qux', 'direction' => 'DESC', 'langcode' => 'ca'],
      ],
      ],
    ];
  }

  /**
   * @covers ::denormalize
   * @dataProvider denormalizeFailProvider
   */
  public function testDenormalizeFail($input) {
    $this->setExpectedException(BadRequestHttpException::class);
    $sort = $this->normalizer->denormalize($input, Sort::class);
  }

  /**
   * Data provider for testDenormalizeFail.
   */
  public function denormalizeFailProvider() {
    return [
      [[['lorem']]],
      [''],
    ];
  }

  /**
   * Provides a mock field resolver.
   */
  protected function getFieldResolver($entity_type_id, $bundle) {
    $field_resolver = $this->prophesize(FieldResolver::class);
    $field_resolver->resolveInternalEntityQueryPath('foo', 'bar', 'lorem')->willReturn('foo');
    $field_resolver->resolveInternalEntityQueryPath('foo', 'bar', 'ipsum')->willReturn('bar');
    $field_resolver->resolveInternalEntityQueryPath('foo', 'bar', 'dolor')->willReturn('baz');
    $field_resolver->resolveInternalEntityQueryPath('foo', 'bar', 'sit')->willReturn('qux');
    return $field_resolver->reveal();
  }

}
