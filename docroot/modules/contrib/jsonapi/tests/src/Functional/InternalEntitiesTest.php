<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestNoLabel;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Makes assertions about the JSON API behavior for internal entities.
 *
 * @group jsonapi
 *
 * @internal
 */
class InternalEntitiesTest extends BrowserTestBase {

  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi',
    'entity_test',
    'serialization',
  ];

  /**
   * A test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * An entity of an internal entity type.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $internalEntity;

  /**
   * An entity referencing an internal entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $referencingEntity;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->testUser = $this->drupalCreateUser([
      'access jsonapi resource list',
      'view test entity',
      'administer entity_test_with_bundle content',
    ], $this->randomString(), TRUE);
    EntityTestBundle::create([
      'id' => 'internal_referencer',
      'label' => 'Entity Test Internal Referencer',
    ])->save();
    $this->createEntityReferenceField(
      'entity_test_with_bundle',
      'internal_referencer',
      'field_internal',
      'Internal Entities',
      'entity_test_no_label'
    );
    $this->internalEntity = EntityTestNoLabel::create([]);
    $this->internalEntity->save();
    $this->referencingEntity = EntityTestWithBundle::create([
      'type' => 'internal_referencer',
      'field_internal' => $this->internalEntity->id(),
    ]);
    $this->referencingEntity->save();
    drupal_flush_all_caches();
  }

  /**
   * Ensures that internal resources types aren't present in the entry point.
   */
  public function testEntryPoint() {
    $this->skipIfIsInternalIsNotSupported();
    $document = $this->jsonapiGet('/jsonapi');
    $this->assertArrayNotHasKey(
      "{$this->internalEntity->getEntityTypeId()}--{$this->internalEntity->bundle()}",
      $document['links'],
      'The entry point should not contain links to internal resource type routes.'
    );
  }

  /**
   * Ensures that internal resources types aren't present in the routes.
   */
  public function testRoutes() {
    $this->skipIfIsInternalIsNotSupported();
    // This cannot be in a data provider because it needs values created by the
    // setUp method.
    $paths = [
      'individual' => $this->getIndividual($this->internalEntity),
      'collection' => $this->jsonapiGet("/jsonapi/{$this->internalEntity->getEntityTypeId()}/{$this->internalEntity->bundle()}"),
      'related' => $this->getRelated($this->referencingEntity, 'field_internal'),
    ];
    foreach ($paths as $type => $document) {
      $this->assertSame(
        404,
        $document['errors'][0]['status'],
        "The '{$type}' route should not be available for internal resource types.'"
      );
    }
  }

  /**
   * Asserts that internal entities are not included in compound documents.
   */
  public function testIncludes() {
    $this->skipIfIsInternalIsNotSupported();
    $document = $this->getIndividual($this->referencingEntity, [
      'query' => ['include' => 'field_internal'],
    ]);
    $this->assertArrayNotHasKey(
      'included',
      $document,
      'Internal entities should not be included in compound documents.'
    );
  }

  /**
   * Asserts that links to internal relationships aren't generated.
   */
  public function testLinks() {
    $this->skipIfIsInternalIsNotSupported();
    $document = $this->getIndividual($this->referencingEntity);
    $this->assertArrayNotHasKey(
      'related',
      $document['data']['relationships']['field_internal']['links'],
      'Links to internal-only related routes should not be in the document.'
    );
  }

  /**
   * Returns the decoded JSON API document for the for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to request.
   * @param array $options
   *   URL options.
   *
   * @return array
   *   The decoded response document.
   */
  protected function getIndividual(EntityInterface $entity, array $options = []) {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $path = "/jsonapi/{$entity_type_id}/{$bundle}/{$entity->uuid()}";
    return $this->jsonapiGet($path, $options);
  }

  /**
   * Performs an authenticated request and returns the decoded document.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to request.
   * @param string $relationship
   *   The field name of the relationship to request.
   * @param array $options
   *   URL options.
   *
   * @return array
   *   The decoded response document.
   */
  protected function getRelated(EntityInterface $entity, $relationship, array $options = []) {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $path = "/jsonapi/{$entity_type_id}/{$bundle}/{$entity->uuid()}/{$relationship}";
    return $this->jsonapiGet($path, $options);
  }

  /**
   * Performs an authenticated request and returns the decoded document.
   */
  protected function jsonapiGet($path, array $options = []) {
    $this->drupalLogin($this->testUser);
    $response = $this->drupalGet($path, $options, ['Accept' => 'application/vnd.api+json']);
    return Json::decode($response);
  }

  /**
   * Only run tests when Drupal version is >= 8.5.
   */
  protected function skipIfIsInternalIsNotSupported() {
    if (floatval(\Drupal::VERSION) < 8.5) {
      $this->markTestSkipped('The Drupal Core version must be >= 8.5');
    }
  }

}
