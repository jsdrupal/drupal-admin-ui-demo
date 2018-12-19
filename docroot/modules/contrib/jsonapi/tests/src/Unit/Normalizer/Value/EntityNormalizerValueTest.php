<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer\Value;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\JsonApiDocumentTopLevelNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\RelationshipNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue
 * @group jsonapi
 *
 * @internal
 */
class EntityNormalizerValueTest extends UnitTestCase {

  /**
   * The EntityNormalizerValue object.
   *
   * @var \Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue
   */
  protected $object;

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

    $field1 = $this->prophesize(FieldNormalizerValueInterface::class);
    $field1->getIncludes()->willReturn([]);
    $field1->getPropertyType()->willReturn('attributes');
    $field1->rasterizeValue()->willReturn('dummy_title');
    $field1->getCacheContexts()->willReturn(['ccbar']);
    $field1->getCacheTags()->willReturn(['ctbar']);
    $field1->getCacheMaxAge()->willReturn(20);
    $field2 = $this->prophesize(RelationshipNormalizerValue::class);
    $field2->getPropertyType()->willReturn('relationships');
    $field2->rasterizeValue()->willReturn(['data' => ['type' => 'node', 'id' => 2]]);
    $field2->getCacheContexts()->willReturn(['ccbaz']);
    $field2->getCacheTags()->willReturn(['ctbaz']);
    $field2->getCacheMaxAge()->willReturn(25);
    $included[] = $this->prophesize(JsonApiDocumentTopLevelNormalizerValue::class);
    $included[0]->getIncludes()->willReturn([]);
    $included[0]->rasterizeValue()->willReturn([
      'data' => [
        'type' => 'node',
        'id' => '199c681d-a9dc-4b6f-a4dc-e3811f24141b',
        'attributes' => ['body' => 'dummy_body1'],
      ],
    ]);
    $included[0]->getCacheContexts()->willReturn(['lorem', 'ipsum']);
    // Type & id duplicated on purpose.
    $included[] = $this->prophesize(JsonApiDocumentTopLevelNormalizerValue::class);
    $included[1]->getIncludes()->willReturn([]);
    $included[1]->rasterizeValue()->willReturn([
      'data' => [
        'type' => 'node',
        'id' => '199c681d-a9dc-4b6f-a4dc-e3811f24141b',
        'attributes' => ['body' => 'dummy_body2'],
      ],
    ]);
    $included[] = $this->prophesize(JsonApiDocumentTopLevelNormalizerValue::class);
    $included[2]->getIncludes()->willReturn([]);
    $included[2]->rasterizeValue()->willReturn([
      'data' => [
        'type' => 'node',
        'id' => '83771375-a4ba-4d7d-a4d5-6153095bb5c5',
        'attributes' => ['body' => 'dummy_body3'],
      ],
    ]);
    $field2->getIncludes()->willReturn(array_map(function ($included_item) {
      return $included_item->reveal();
    }, $included));
    $context = [
      'resource_type' => new ResourceType('node', 'article',
        NodeInterface::class),
    ];
    $entity = $this->prophesize(EntityInterface::class);
    $entity->uuid()->willReturn('248150b2-79a2-4b44-9f49-bf405a51414a');
    $entity->isNew()->willReturn(FALSE);
    $entity->getEntityTypeId()->willReturn('node');
    $entity->bundle()->willReturn('article');
    $entity->getCacheContexts()->willReturn(['ccfoo']);
    $entity->getCacheTags()->willReturn(['ctfoo']);
    $entity->getCacheMaxAge()->willReturn(15);
    $link_manager = $this->prophesize(LinkManager::class);
    $link_manager
      ->getEntityLink(Argument::any(), Argument::any(), Argument::type('array'), Argument::type('string'))
      ->willReturn('dummy_entity_link');

    // Stub the addCacheableDependency on the SUT. We'll test the cacheable
    // metadata bubbling using Kernel tests.
    $this->object = $this->getMockBuilder(EntityNormalizerValue::class)
      ->setMethods(['addCacheableDependency'])
      ->setConstructorArgs([
        ['title' => $field1->reveal(), 'field_related' => $field2->reveal()],
        $context,
        $entity->reveal(),
        ['link_manager' => $link_manager->reveal()],
      ])
      ->getMock();
    $this->object->method('addCacheableDependency');
  }

  /**
   * @covers ::__construct
   */
  public function testCacheability() {
    $this->assertSame(['ccbar', 'ccbaz', 'ccfoo'], $this->object->getCacheContexts());
    $this->assertSame(['ctbar', 'ctbaz', 'ctfoo'], $this->object->getCacheTags());
    $this->assertSame(15, $this->object->getCacheMaxAge());
  }

  /**
   * @covers ::rasterizeValue
   */
  public function testRasterizeValue() {
    $this->assertEquals([
      'type' => 'node--article',
      'id' => '248150b2-79a2-4b44-9f49-bf405a51414a',
      'attributes' => ['title' => 'dummy_title'],
      'relationships' => [
        'field_related' => ['data' => ['type' => 'node', 'id' => 2]],
      ],
      'links' => [
        'self' => 'dummy_entity_link',
      ],
    ], $this->object->rasterizeValue());
  }

  /**
   * @covers ::rasterizeIncludes
   */
  public function testRasterizeIncludes() {
    $expected = [
      [
        'data' => [
          'type' => 'node',
          'id' => '199c681d-a9dc-4b6f-a4dc-e3811f24141b',
          'attributes' => ['body' => 'dummy_body1'],
        ],
      ],
      [
        'data' => [
          'type' => 'node',
          'id' => '199c681d-a9dc-4b6f-a4dc-e3811f24141b',
          'attributes' => ['body' => 'dummy_body2'],
        ],
      ],
      [
        'data' => [
          'type' => 'node',
          'id' => '83771375-a4ba-4d7d-a4d5-6153095bb5c5',
          'attributes' => ['body' => 'dummy_body3'],
        ],
      ],
    ];
    $this->assertEquals($expected, $this->object->rasterizeIncludes());
  }

  /**
   * @covers ::getIncludes
   */
  public function testGetIncludes() {
    $includes = $this->object->getIncludes();
    $includes = array_filter($includes, function ($included) {
      return $included instanceof JsonApiDocumentTopLevelNormalizerValue;
    });
    $this->assertCount(3, $includes);
  }

}
