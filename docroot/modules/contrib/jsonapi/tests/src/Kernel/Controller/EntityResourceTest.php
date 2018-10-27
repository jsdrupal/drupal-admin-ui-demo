<?php

namespace Drupal\Tests\jsonapi\Kernel\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Controller\EntityResource;
use Drupal\jsonapi\Resource\EntityCollection;
use Drupal\jsonapi\Resource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\Query\EntityCondition;
use Drupal\jsonapi\Query\EntityConditionGroup;
use Drupal\jsonapi\Query\Filter;
use Drupal\jsonapi\Query\Sort;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @coversDefaultClass \Drupal\jsonapi\Controller\EntityResource
 * @group jsonapi
 * @group legacy
 *
 * @internal
 */
class EntityResourceTest extends JsonapiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'field',
    'jsonapi',
    'serialization',
    'system',
    'user',
  ];

  /**
   * The user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * The node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * The other node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node2;

  /**
   * An unpublished node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node3;

  /**
   * A fake request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    // Add the additional table schemas.
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    NodeType::create([
      'type' => 'lorem',
    ])->save();
    $type = NodeType::create([
      'type' => 'article',
    ]);
    $type->save();
    $this->user = User::create([
      'name' => 'user1',
      'mail' => 'user@localhost',
      'status' => 1,
      'roles' => ['test_role_one', 'test_role_two'],
    ]);
    $this->createEntityReferenceField('node', 'article', 'field_relationships', 'Relationship', 'node', 'default', ['target_bundles' => ['article']], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $this->user->save();

    $this->node = Node::create([
      'title' => 'dummy_title',
      'type' => 'article',
      'uid' => $this->user->id(),
    ]);
    $this->node->save();

    $this->node2 = Node::create([
      'type' => 'article',
      'title' => 'Another test node',
      'uid' => $this->user->id(),
    ]);
    $this->node2->save();

    $this->node3 = Node::create([
      'type' => 'article',
      'title' => 'Unpublished test node',
      'uid' => $this->user->id(),
      'status' => 0,
    ]);
    $this->node3->save();

    $this->node4 = Node::create([
      'type' => 'article',
      'title' => 'Test node with related nodes',
      'uid' => $this->user->id(),
      'field_relationships' => [
        ['target_id' => $this->node->id()],
        ['target_id' => $this->node2->id()],
        ['target_id' => $this->node3->id()],
      ],
    ]);
    $this->node4->save();

    // Give anonymous users permission to view user profiles, so that we can
    // verify the cache tags of cached versions of user profile pages.
    array_map(function ($role_id) {
      Role::create([
        'id' => $role_id,
        'permissions' => [
          'access user profiles',
          'access content',
        ],
      ])->save();
    }, [RoleInterface::ANONYMOUS_ID, 'test_role_one', 'test_role_two']);
  }

  /**
   * @covers ::getIndividual
   */
  public function testGetIndividual() {
    $entity_resource = $this->buildEntityResource('node', 'article');
    $response = $entity_resource->getIndividual($this->node, new Request());
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertEquals(1, $response->getResponseData()->getData()->id());
  }

  /**
   * @covers ::getIndividual
   */
  public function testGetIndividualDenied() {
    $role = Role::load(RoleInterface::ANONYMOUS_ID);
    $role->revokePermission('access content');
    $role->save();
    $entity_resource = $this->buildEntityResource('node', 'article');
    $this->setExpectedException(EntityAccessDeniedHttpException::class);
    $entity_resource->getIndividual($this->node, new Request());
  }

  /**
   * @covers ::getCollection
   */
  public function testGetCollection() {
    $request = new Request([], [], [
      '_route_params' => ['_json_api_params' => []],
      '_json_api_params' => [],
    ]);

    // Get the response.
    $entity_resource = $this->buildEntityResource('node', 'article');
    $response = $entity_resource->getCollection($request);

    // Assertions.
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertInstanceOf(EntityCollection::class, $response->getResponseData()->getData());
    $this->assertEquals(1, $response->getResponseData()->getData()->getIterator()->current()->id());
    $this->assertEquals([
      'node:1',
      'node:2',
      'node:3',
      'node:4',
      'node_list',
    ], $response->getCacheableMetadata()->getCacheTags());
  }

  /**
   * @covers ::getCollection
   */
  public function testGetFilteredCollection() {
    $filter = new Filter(new EntityConditionGroup('AND', [new EntityCondition('type', 'article')]));
    $request = new Request([], [], [
      '_route_params' => [
        '_json_api_params' => [
          'filter' => $filter,
        ],
      ],
      '_json_api_params' => [
        'filter' => $filter,
      ],
    ]);

    $entity_resource = new EntityResource(
      $this->container->get('jsonapi.resource_type.repository')->get('node_type', 'node_type'),
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('plugin.manager.field.field_type'),
      $this->container->get('jsonapi.link_manager'),
      $this->container->get('jsonapi.resource_type.repository')
    );

    // Get the response.
    $response = $entity_resource->getCollection($request);

    // Assertions.
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertInstanceOf(EntityCollection::class, $response->getResponseData()->getData());
    $this->assertCount(1, $response->getResponseData()->getData());
    $this->assertEquals(['config:node_type_list'], $response->getCacheableMetadata()->getCacheTags());
  }

  /**
   * @covers ::getCollection
   */
  public function testGetSortedCollection() {
    $sort = new Sort([['path' => 'type', 'direction' => 'DESC']]);
    $request = new Request([], [], [
      '_route_params' => [
        '_json_api_params' => [
          'sort' => $sort,
        ],
      ],
      '_json_api_params' => [
        'sort' => $sort,
      ],
    ]);

    $entity_resource = new EntityResource(
      $this->container->get('jsonapi.resource_type.repository')->get('node_type', 'node_type'),
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('plugin.manager.field.field_type'),
      $this->container->get('jsonapi.link_manager'),
      $this->container->get('jsonapi.resource_type.repository')
    );

    // Get the response.
    $response = $entity_resource->getCollection($request);

    // Assertions.
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertInstanceOf(EntityCollection::class, $response->getResponseData()->getData());
    $this->assertCount(2, $response->getResponseData()->getData());
    $this->assertEquals($response->getResponseData()->getData()->toArray()[0]->id(), 'lorem');
    $this->assertEquals(['config:node_type_list'], $response->getCacheableMetadata()->getCacheTags());
  }

  /**
   * @covers ::getCollection
   */
  public function testGetPagedCollection() {
    $pager = new OffsetPage(1, 1);
    $request = new Request([], [], [
      '_route_params' => [
        '_json_api_params' => [
          'page' => $pager,
        ],
      ],
      '_json_api_params' => [
        'page' => $pager,
      ],
    ]);

    $entity_resource = new EntityResource(
      $this->container->get('jsonapi.resource_type.repository')->get('node', 'article'),
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('plugin.manager.field.field_type'),
      $this->container->get('jsonapi.link_manager'),
      $this->container->get('jsonapi.resource_type.repository')
    );

    // Get the response.
    $response = $entity_resource->getCollection($request);

    // Assertions.
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertInstanceOf(EntityCollection::class, $response->getResponseData()->getData());
    $data = $response->getResponseData()->getData();
    $this->assertCount(1, $data);
    $this->assertEquals(2, $data->toArray()[0]->id());
    $this->assertEquals(['node:2', 'node_list'], $response->getCacheableMetadata()->getCacheTags());
  }

  /**
   * @covers ::getCollection
   */
  public function testGetEmptyCollection() {
    $filter = new Filter(new EntityConditionGroup('AND', [new EntityCondition('uuid', 'invalid')]));
    $request = new Request([], [], [
      '_route_params' => [
        '_json_api_params' => [
          'filter' => $filter,
        ],
      ],
      '_json_api_params' => [
        'filter' => $filter,
      ],
    ]);

    // Get the response.
    $entity_resource = $this->buildEntityResource('node', 'article');
    $response = $entity_resource->getCollection($request);

    // Assertions.
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertInstanceOf(EntityCollection::class, $response->getResponseData()->getData());
    $this->assertEquals(0, $response->getResponseData()->getData()->count());
    $this->assertEquals(['node_list'], $response->getCacheableMetadata()->getCacheTags());
  }

  /**
   * @covers ::getRelated
   */
  public function testGetRelated() {
    // to-one relationship.
    $entity_resource = $this->buildEntityResource('node', 'article', [
      'uid' => [new ResourceType('user', 'user', NULL)],
      'roles' => [new ResourceType('user_role', 'user_role', NULL)],
      'field_relationships' => [new ResourceType('node', 'article', NULL)],
    ]);
    $response = $entity_resource->getRelated($this->node, 'uid', new Request());
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertInstanceOf(User::class, $response->getResponseData()
      ->getData());
    $this->assertEquals(1, $response->getResponseData()->getData()->id());
    $this->assertEquals(
      ['node:1', 'user:1'],
      $response->getCacheableMetadata()->getCacheTags()
    );
    // to-many relationship.
    $response = $entity_resource->getRelated($this->node4, 'field_relationships', new Request());
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response
      ->getResponseData());
    $this->assertInstanceOf(EntityCollection::class, $response
      ->getResponseData()
      ->getData());
    $this->assertEquals(
      ['node:1', 'node:2', 'node:3', 'node:4'],
      $response->getCacheableMetadata()->getCacheTags()
    );
  }

  /**
   * @covers ::getRelationship
   */
  public function testGetRelationship() {
    // to-one relationship.
    $entity_resource = $this->buildEntityResource('node', 'article', [
      'uid' => [new ResourceType('user', 'user', NULL)],
    ]);
    $response = $entity_resource->getRelationship($this->node, 'uid', new Request());
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertInstanceOf(
      EntityReferenceFieldItemListInterface::class,
      $response->getResponseData()->getData()
    );
    $this->assertEquals(1, $response
      ->getResponseData()
      ->getData()
      ->getEntity()
      ->id()
    );
    $this->assertEquals('node', $response
      ->getResponseData()
      ->getData()
      ->getEntity()
      ->getEntityTypeId()
    );
  }

  /**
   * @covers ::createIndividual
   */
  public function testCreateIndividual() {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Lorem ipsum',
    ]);
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('create article content')
      ->save();
    $entity_resource = $this->buildEntityResource('node', 'article');
    $response = $entity_resource->createIndividual($node, new Request());
    // As a side effect, the node will also be saved.
    $this->assertNotEmpty($node->id());
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertEquals(5, $response->getResponseData()->getData()->id());
    $this->assertEquals(201, $response->getStatusCode());
  }

  /**
   * @covers ::createIndividual
   */
  public function testCreateIndividualWithMissingRequiredData() {
    $node = Node::create([
      'type' => 'article',
      // No title specified, even if its required.
    ]);
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('create article content')
      ->save();
    $this->setExpectedException(HttpException::class, 'Unprocessable Entity: validation failed.');
    $entity_resource = $this->buildEntityResource('node', 'article');
    $entity_resource->createIndividual($node, new Request());
  }

  /**
   * @covers ::createIndividual
   */
  public function testCreateIndividualConfig() {
    $node_type = NodeType::create([
      'type' => 'test',
      'name' => 'Test Type',
      'description' => 'Lorem ipsum',
    ]);
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('administer content types')
      ->save();
    $entity_resource = $this->buildEntityResource('node', 'article');
    $response = $entity_resource->createIndividual($node_type, new Request());
    // As a side effect, the node type will also be saved.
    $this->assertNotEmpty($node_type->id());
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertEquals('test', $response->getResponseData()->getData()->id());
    $this->assertEquals(201, $response->getStatusCode());
  }

  /**
   * @covers ::createIndividual
   */
  public function testCreateIndividualDuplicateError() {
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('create article content')
      ->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Lorem ipsum',
    ]);
    $node->save();
    $node->enforceIsNew();

    $this->setExpectedException(ConflictHttpException::class, 'Conflict: Entity already exists.');
    $entity_resource = $this->buildEntityResource('node', 'article');
    $entity_resource->createIndividual($node, new Request());
  }

  /**
   * @covers ::patchIndividual
   * @dataProvider patchIndividualProvider
   */
  public function testPatchIndividual($values) {
    $parsed_node = Node::create($values);
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('edit any article content')
      ->save();
    $payload = Json::encode([
      'data' => [
        'type' => 'article',
        'id' => $this->node->uuid(),
        'attributes' => [
          'title' => '',
          'field_relationships' => '',
        ],
      ],
    ]);
    $request = new Request([], [], [], [], [], [], $payload);

    // Create a new EntityResource that uses uuid.
    $entity_resource = $this->buildEntityResource('node', 'article');
    $response = $entity_resource->patchIndividual($this->node, $parsed_node, $request);

    // As a side effect, the node will also be saved.
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $updated_node = $response->getResponseData()->getData();
    $this->assertInstanceOf(Node::class, $updated_node);
    $this->assertSame($values['title'], $this->node->getTitle());
    $this->assertSame($values['field_relationships'], $this->node->get('field_relationships')->getValue());
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Provides data for the testPatchIndividual.
   *
   * @return array
   *   The input data for the test function.
   */
  public function patchIndividualProvider() {
    return [
      [
        [
          'type' => 'article',
          'title' => 'PATCHED',
          'field_relationships' => [['target_id' => 1]],
        ],
      ],
    ];
  }

  /**
   * @covers ::patchIndividual
   * @dataProvider patchIndividualConfigProvider
   */
  public function testPatchIndividualConfig($values) {
    // List of fields to be ignored.
    $ignored_fields = ['uuid', 'entityTypeId', 'type'];
    $node_type = NodeType::create([
      'type' => 'test',
      'name' => 'Test Type',
      'description' => '',
    ]);
    $node_type->save();

    $parsed_node_type = NodeType::create($values);
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('administer content types')
      ->save();
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('edit any article content')
      ->save();
    $payload = Json::encode([
      'data' => [
        'type' => 'node_type',
        'id' => $node_type->uuid(),
        'attributes' => $values,
      ],
    ]);
    $request = new Request([], [], [], [], [], [], $payload);

    $entity_resource = $this->buildEntityResource('node', 'article');
    $response = $entity_resource->patchIndividual($node_type, $parsed_node_type, $request);

    // As a side effect, the node will also be saved.
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $updated_node_type = $response->getResponseData()->getData();
    $this->assertInstanceOf(NodeType::class, $updated_node_type);
    // If the field is ignored then we should not see a difference.
    foreach ($values as $field_name => $value) {
      in_array($field_name, $ignored_fields) ?
        $this->assertNotSame($value, $node_type->get($field_name)) :
        $this->assertSame($value, $node_type->get($field_name));
    }
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Provides data for the testPatchIndividualConfig.
   *
   * @return array
   *   The input data for the test function.
   */
  public function patchIndividualConfigProvider() {
    return [
      [['description' => 'PATCHED', 'status' => FALSE]],
      [[]],
    ];
  }

  /**
   * @covers ::patchIndividual
   * @dataProvider patchIndividualConfigFailedProvider
   */
  public function testPatchIndividualFailedConfig($values) {
    $this->setExpectedException(ConfigException::class);
    $this->testPatchIndividualConfig($values);
  }

  /**
   * Provides data for the testPatchIndividualFailedConfig.
   *
   * @return array
   *   The input data for the test function.
   */
  public function patchIndividualConfigFailedProvider() {
    return [
      [['uuid' => 'PATCHED']],
      [['type' => 'article', 'status' => FALSE]],
    ];
  }

  /**
   * @covers ::deleteIndividual
   */
  public function testDeleteIndividual() {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Lorem ipsum',
    ]);
    $nid = $node->id();
    $node->save();
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('delete own article content')
      ->save();
    $entity_resource = $this->buildEntityResource('node', 'article');
    $response = $entity_resource->deleteIndividual($node, new Request());
    // As a side effect, the node will also be deleted.
    $count = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->condition('nid', $nid)
      ->count()
      ->execute();
    $this->assertEquals(0, $count);
    $this->assertNull($response->getResponseData());
    $this->assertEquals(204, $response->getStatusCode());
  }

  /**
   * @covers ::deleteIndividual
   */
  public function testDeleteIndividualConfig() {
    $node_type = NodeType::create([
      'type' => 'test',
      'name' => 'Test Type',
      'description' => 'Lorem ipsum',
    ]);
    $id = $node_type->id();
    $node_type->save();
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('administer content types')
      ->save();
    $entity_resource = $this->buildEntityResource('node', 'article');
    $response = $entity_resource->deleteIndividual($node_type, new Request());
    // As a side effect, the node will also be deleted.
    $count = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->getQuery()
      ->condition('type', $id)
      ->count()
      ->execute();
    $this->assertEquals(0, $count);
    $this->assertNull($response->getResponseData());
    $this->assertEquals(204, $response->getStatusCode());
  }

  /**
   * @covers ::createRelationship
   */
  public function testCreateRelationship() {
    $parsed_field_list = $this->container
      ->get('plugin.manager.field.field_type')
      ->createFieldItemList($this->node, 'field_relationships', [
        ['target_id' => $this->node->id()],
      ]);
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('edit any article content')
      ->save();

    $entity_resource = $this->buildEntityResource('node', 'article', [
      'field_relationships' => [new ResourceType('node', 'article', NULL)],
    ]);
    $response = $entity_resource->createRelationship($this->node, 'field_relationships', $parsed_field_list, new Request());

    // As a side effect, the node will also be saved.
    $this->assertNotEmpty($this->node->id());
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $field_list = $response->getResponseData()->getData();
    $this->assertInstanceOf(EntityReferenceFieldItemListInterface::class, $field_list);
    $this->assertSame('field_relationships', $field_list->getName());
    $this->assertEquals([['target_id' => 1]], $field_list->getValue());
    $this->assertEquals(204, $response->getStatusCode());
  }

  /**
   * @covers ::patchRelationship
   * @dataProvider patchRelationshipProvider
   */
  public function testPatchRelationship($relationships) {
    $this->node->field_relationships->appendItem(['target_id' => $this->node->id()]);
    $this->node->save();
    $parsed_field_list = $this->container
      ->get('plugin.manager.field.field_type')
      ->createFieldItemList($this->node, 'field_relationships', $relationships);
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('edit any article content')
      ->save();

    $entity_resource = $this->buildEntityResource('node', 'article', [
      'field_relationships' => [new ResourceType('node', 'article', NULL)],
    ]);
    $response = $entity_resource->patchRelationship($this->node, 'field_relationships', $parsed_field_list, new Request());

    // As a side effect, the node will also be saved.
    $this->assertNotEmpty($this->node->id());
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $field_list = $response->getResponseData()->getData();
    $this->assertInstanceOf(EntityReferenceFieldItemListInterface::class, $field_list);
    $this->assertSame('field_relationships', $field_list->getName());
    $this->assertEquals($relationships, $field_list->getValue());
    $this->assertEquals(204, $response->getStatusCode());
  }

  /**
   * Provides data for the testPatchRelationship.
   *
   * @return array
   *   The input data for the test function.
   */
  public function patchRelationshipProvider() {
    return [
      // Replace relationships.
      [[['target_id' => 2], ['target_id' => 1]]],
      // Remove relationships.
      [[]],
    ];
  }

  /**
   * @covers ::deleteRelationship
   * @dataProvider deleteRelationshipProvider
   */
  public function testDeleteRelationship($deleted_rels, $kept_rels) {
    $this->node->field_relationships->appendItem(['target_id' => $this->node->id()]);
    $this->node->field_relationships->appendItem(['target_id' => $this->node2->id()]);
    $this->node->save();
    $parsed_field_list = $this->container
      ->get('plugin.manager.field.field_type')
      ->createFieldItemList($this->node, 'field_relationships', $deleted_rels);
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('edit any article content')
      ->save();

    $entity_resource = $this->buildEntityResource('node', 'article', [
      'field_relationships' => [new ResourceType('node', 'article', NULL)],
    ]);
    $response = $entity_resource->deleteRelationship($this->node, 'field_relationships', $parsed_field_list, new Request());

    // As a side effect, the node will also be saved.
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $field_list = $response->getResponseData()->getData();
    $this->assertInstanceOf(EntityReferenceFieldItemListInterface::class, $field_list);
    $this->assertSame('field_relationships', $field_list->getName());
    $this->assertEquals($kept_rels, $field_list->getValue());
    $this->assertEquals(204, $response->getStatusCode());
  }

  /**
   * @covers ::getRelated
   */
  public function testGetRelatedInternal() {
    $internal_resource_type = new ResourceType('node', 'article', NULL, TRUE);
    $resource = $this->buildEntityResource('node', 'article', [
      'field_relationships' => [$internal_resource_type],
    ]);

    $this->setExpectedException(NotFoundHttpException::class);
    $resource->getRelationship($this->node, 'field_relationships', new Request());
  }

  /**
   * @covers ::getRelationship
   */
  public function testGetRelationshipInternal() {
    $internal_resource_type = new ResourceType('node', 'article', NULL, TRUE);
    $resource = $this->buildEntityResource('node', 'article', [
      'field_relationships' => [$internal_resource_type],
    ]);

    $this->setExpectedException(NotFoundHttpException::class);
    $resource->getRelationship($this->node, 'field_relationships', new Request());
  }

  /**
   * @covers ::createRelationship
   */
  public function testCreateRelationshipInternal() {
    $internal_resource_type = new ResourceType('node', 'article', NULL, TRUE);
    $resource = $this->buildEntityResource('node', 'article', [
      'field_relationships' => [$internal_resource_type],
    ]);

    Role::load(Role::ANONYMOUS_ID)->grantPermission('edit any article content')->save();

    $field_type_manager = $this->container->get('plugin.manager.field.field_type');
    $list = $field_type_manager->createFieldItemList($this->node, 'field_relationships');

    $this->setExpectedException(NotFoundHttpException::class);
    $resource->createRelationship($this->node, 'field_relationships', $list, new Request());
  }

  /**
   * @covers ::patchRelationship
   */
  public function testPatchRelationshipInternal() {
    $internal_resource_type = new ResourceType('node', 'article', NULL, TRUE);
    $resource = $this->buildEntityResource('node', 'article', [
      'field_relationships' => [$internal_resource_type],
    ]);

    Role::load(Role::ANONYMOUS_ID)->grantPermission('edit any article content')->save();

    $field_type_manager = $this->container->get('plugin.manager.field.field_type');
    $list = $field_type_manager->createFieldItemList($this->node, 'field_relationships');

    $this->setExpectedException(NotFoundHttpException::class);
    $resource->patchRelationship($this->node, 'field_relationships', $list, new Request());
  }

  /**
   * @covers ::deleteRelationship
   */
  public function testDeleteRelationshipInternal() {
    $internal_resource_type = new ResourceType('node', 'article', NULL, TRUE);
    $resource = $this->buildEntityResource('node', 'article', [
      'field_relationships' => [$internal_resource_type],
    ]);

    Role::load(Role::ANONYMOUS_ID)->grantPermission('edit any article content')->save();

    $field_type_manager = $this->container->get('plugin.manager.field.field_type');
    $list = $field_type_manager->createFieldItemList($this->node, 'field_relationships');

    $this->setExpectedException(NotFoundHttpException::class);
    $resource->deleteRelationship($this->node, 'field_relationships', $list, new Request());
  }

  /**
   * Provides data for the testDeleteRelationship.
   *
   * @return array
   *   The input data for the test function.
   */
  public function deleteRelationshipProvider() {
    return [
      // Remove one relationship.
      [[['target_id' => 1]], [['target_id' => 2]]],
      // Remove all relationships.
      [[['target_id' => 2], ['target_id' => 1]], []],
      // Remove no relationship.
      [[], [['target_id' => 1], ['target_id' => 2]]],
    ];
  }

  /**
   * Instantiates a test EntityResource.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param \Drupal\jsonapi\ResourceType\ResourceType[] $relatable_resource_types
   *   An array of relatable resource types, keyed by field.
   * @param bool $internal
   *   Whether the primary resource type is internal.
   *
   * @return \Drupal\jsonapi\Controller\EntityResource
   *   The resource.
   */
  protected function buildEntityResource($entity_type_id, $bundle, array $relatable_resource_types = [], $internal = FALSE) {
    // Get the entity resource.
    $resource_type = new ResourceType($entity_type_id, $bundle, NULL, $internal);
    $resource_type->setRelatableResourceTypes($relatable_resource_types);

    return new EntityResource(
      $resource_type,
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('plugin.manager.field.field_type'),
      $this->container->get('jsonapi.link_manager'),
      $this->container->get('jsonapi.resource_type.repository')
    );
  }

}
