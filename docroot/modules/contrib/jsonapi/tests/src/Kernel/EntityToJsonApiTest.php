<?php

namespace Drupal\Tests\jsonapi\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\file\Entity\File;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\jsonapi\EntityToJsonApi
 * @group jsonapi
 * @group jsonapi_serializer
 * @group legacy
 *
 * @internal
 */
class EntityToJsonApiTest extends JsonapiKernelTestBase {

  use ImageFieldCreationTrait;

  /**
   * System under test.
   *
   * @var \Drupal\jsonapi\EntityToJsonApi
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'jsonapi',
    'field',
    'node',
    'serialization',
    'system',
    'taxonomy',
    'text',
    'user',
    'file',
    'image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    // Add the additional table schemas.
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->nodeType = NodeType::create([
      'type' => 'article',
    ]);
    $this->nodeType->save();
    $this->createEntityReferenceField(
      'node',
      'article',
      'field_tags',
      'Tags',
      'taxonomy_term',
      'default',
      ['target_bundles' => ['tags']],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    $this->createImageField('field_image', 'article');

    $this->user = User::create([
      'name' => 'user1',
      'mail' => 'user@localhost',
    ]);
    $this->user2 = User::create([
      'name' => 'user2',
      'mail' => 'user2@localhost',
    ]);

    $this->user->save();
    $this->user2->save();

    $this->vocabulary = Vocabulary::create(['name' => 'Tags', 'vid' => 'tags']);
    $this->vocabulary->save();

    $this->term1 = Term::create([
      'name' => 'term1',
      'vid' => $this->vocabulary->id(),
    ]);
    $this->term2 = Term::create([
      'name' => 'term2',
      'vid' => $this->vocabulary->id(),
    ]);

    $this->term1->save();
    $this->term2->save();

    $this->file = File::create([
      'uri' => 'public://example.png',
      'filename' => 'example.png',
    ]);
    $this->file->save();

    $this->node = Node::create([
      'title' => 'dummy_title',
      'type' => 'article',
      'uid' => 1,
      'field_tags' => [
        ['target_id' => $this->term1->id()],
        ['target_id' => $this->term2->id()],
      ],
      'field_image' => [
        [
          'target_id' => $this->file->id(),
          'alt' => 'test alt',
          'title' => 'test title',
          'width' => 10,
          'height' => 11,
        ],
      ],
    ]);

    $this->node->save();

    $link_manager = $this->prophesize(LinkManager::class);
    $link_manager
      ->getEntityLink(Argument::any(), Argument::any(), Argument::type('array'), Argument::type('string'))
      ->willReturn('dummy_entity_link');
    $link_manager
      ->getRequestLink(Argument::any())
      ->willReturn('dummy_document_link');
    $this->container->set('jsonapi.link_manager', $link_manager->reveal());

    $this->nodeType = NodeType::load('article');

    $this->role = Role::create([
      'id' => RoleInterface::ANONYMOUS_ID,
      'permissions' => [
        'access content',
      ],
    ]);
    $this->role->save();
    $this->sut = \Drupal::service('jsonapi.entity.to_jsonapi');
  }

  /**
   * @covers ::serialize
   * @covers ::normalize
   */
  public function testSerialize() {
    $entities = [
      $this->node,
      $this->user,
      $this->file,
      $this->term1,
      // Make sure we also support configuration entities.
      $this->vocabulary,
      $this->nodeType,
      $this->role,
    ];
    array_walk(
      $entities,
      function ($entity) {
        $output = $this->sut->serialize($entity);
        $this->assertInternalType('string', $output);
        $this->assertJsonApi(Json::decode($output));
        $output = $this->sut->normalize($entity);
        $this->assertInternalType('array', $output);
        $this->assertJsonApi($output);
      }
    );
  }

  /**
   * Helper to assert if a string is valid JSON API.
   *
   * @param array $structured
   *   The JSON API data to check.
   */
  protected function assertJsonApi(array $structured) {
    $this->assertNotEmpty($structured['data']['type']);
    $this->assertNotEmpty($structured['data']['id']);
    $this->assertNotEmpty($structured['data']['attributes']);
    $this->assertInternalType('string', $structured['links']['self']);
  }

}
