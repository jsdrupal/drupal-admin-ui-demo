<?php

namespace Drupal\Tests\jsonapi\Kernel\Query;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;

/**
 * @coversDefaultClass \Drupal\jsonapi\Query\Filter
 * @group jsonapi
 * @group jsonapi_query
 * @group legacy
 *
 * @internal
 */
class FilterTest extends JsonapiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'jsonapi',
    'node',
    'serialization',
    'system',
    'text',
    'user',
  ];

  /**
   * The filter denormalizer.
   *
   * @var \Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $normalizer;

  /**
   * A node storage instance.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->setUpSchemas();

    $this->savePaintingType();

    // ((RED or CIRCLE) or (YELLOW and SQUARE))
    $this->savePaintings([
      ['colors' => ['red'], 'shapes' => ['triangle'], 'title' => 'FIND'],
      ['colors' => ['orange'], 'shapes' => ['circle'], 'title' => 'FIND'],
      ['colors' => ['orange'], 'shapes' => ['triangle'], 'title' => 'DONT_FIND'],
      ['colors' => ['yellow'], 'shapes' => ['square'], 'title' => 'FIND'],
      ['colors' => ['yellow'], 'shapes' => ['triangle'], 'title' => 'DONT_FIND'],
      ['colors' => ['orange'], 'shapes' => ['square'], 'title' => 'DONT_FIND'],
    ]);

    $this->normalizer = $this->container->get('serializer.normalizer.filter.jsonapi');
    $this->nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');
  }

  /**
   * @covers ::queryCondition
   */
  public function testQueryCondition() {
    // Can't use a data provider because we need access to the container.
    $data = $this->queryConditionData();

    foreach ($data as $case) {
      $normalized = $case[0];
      $expected_query = $case[1];
      // Denormalize the test filter into the object we want to test.
      $filter = $this->normalizer->denormalize($normalized, Filter::class, NULL, [
        'entity_type_id' => 'node',
        'bundle' => 'painting',
      ]);

      $query = $this->nodeStorage->getQuery();

      // Get the query condition parsed from the input.
      $condition = $filter->queryCondition($query);

      // Apply it to the query.
      $query->condition($condition);

      // Compare the results.
      $this->assertEquals($expected_query->execute(), $query->execute());
    }
  }

  /**
   * Simply provides test data to keep the actual test method tidy.
   */
  protected function queryConditionData() {
    // ((RED or CIRCLE) or (YELLOW and SQUARE))
    $query = $this->nodeStorage->getQuery();

    $or_group = $query->orConditionGroup();

    $nested_or_group = $query->orConditionGroup();
    $nested_or_group->condition('colors', 'red', 'CONTAINS');
    $nested_or_group->condition('shapes', 'circle', 'CONTAINS');
    $or_group->condition($nested_or_group);

    $nested_and_group = $query->andConditionGroup();
    $nested_and_group->condition('colors', 'yellow', 'CONTAINS');
    $nested_and_group->condition('shapes', 'square', 'CONTAINS');
    $or_group->condition($nested_and_group);

    $query->condition($or_group);

    return [
      [
        [
          'or-group' => ['group' => ['conjunction' => 'OR']],
          'nested-or-group' => ['group' => ['conjunction' => 'OR', 'memberOf' => 'or-group']],
          'nested-and-group' => ['group' => ['conjunction' => 'AND', 'memberOf' => 'or-group']],
          'condition-0' => [
            'condition' => [
              'path' => 'colors',
              'value' => 'red',
              'operator' => 'CONTAINS',
              'memberOf' => 'nested-or-group',
            ],
          ],
          'condition-1' => [
            'condition' => [
              'path' => 'shapes',
              'value' => 'circle',
              'operator' => 'CONTAINS',
              'memberOf' => 'nested-or-group',
            ],
          ],
          'condition-2' => [
            'condition' => [
              'path' => 'colors',
              'value' => 'yellow',
              'operator' =>
              'CONTAINS',
              'memberOf' => 'nested-and-group',
            ],
          ],
          'condition-3' => [
            'condition' => [
              'path' => 'shapes',
              'value' => 'square',
              'operator' => 'CONTAINS',
              'memberOf' => 'nested-and-group',
            ],
          ],
        ],
        $query,
      ],
    ];
  }

  /**
   * Sets up the schemas.
   */
  protected function setUpSchemas() {
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);

    $this->installSchema('user', []);
    foreach (['user', 'node'] as $entity_type_id) {
      $this->installEntitySchema($entity_type_id);
    }
  }

  /**
   * Creates a painting node type.
   */
  protected function savePaintingType() {
    NodeType::create([
      'type' => 'painting',
    ])->save();
    $this->createTextField(
      'node', 'painting',
      'colors', 'Colors',
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
    $this->createTextField(
      'node', 'painting',
      'shapes', 'Shapes',
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
  }

  /**
   * Creates painting nodes.
   */
  protected function savePaintings($paintings) {
    foreach ($paintings as $painting) {
      Node::create(array_merge([
        'type' => 'painting',
      ], $painting))->save();
    }
  }

}
