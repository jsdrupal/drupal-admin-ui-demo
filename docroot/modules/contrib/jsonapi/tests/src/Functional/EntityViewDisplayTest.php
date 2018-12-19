<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;

/**
 * JSON API integration test for the "EntityViewDisplay" config entity type.
 *
 * @group jsonapi
 */
class EntityViewDisplayTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'entity_view_display';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'entity_view_display--entity_view_display';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer node display']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "Camelids" node type.
    $camelids = NodeType::create([
      'name' => 'Camelids',
      'type' => 'camelids',
    ]);
    $camelids->save();

    // Create a view display.
    $view_display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'camelids',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $view_display->save();

    return $view_display;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/entity_view_display/entity_view_display/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    $document = [
      'jsonapi' => [
        'meta' => [
          'links' => [
            'self' => 'http://jsonapi.org/format/1.0/',
          ],
        ],
        'version' => '1.0',
      ],
      'links' => [
        'self' => $self_url,
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'entity_view_display--entity_view_display',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'bundle' => 'camelids',
          'content' => [
            'links' => [
              'region' => 'content',
              'weight' => 100,
            ],
          ],
          'dependencies' => [
            'config' => [
              'node.type.camelids',
            ],
            'module' => [
              'user',
            ],
          ],
          'hidden' => [],
          'id' => 'node.camelids.default',
          'langcode' => 'en',
          'mode' => 'default',
          'status' => TRUE,
          'targetEntityType' => 'node',
          'uuid' => $this->entity->uuid(),
        ],
      ],
    ];
    if (floatval(\Drupal::VERSION) >= 8.6) {
      $document['data']['attributes']['content']['links']['settings'] = [];
      $document['data']['attributes']['content']['links']['third_party_settings'] = [];
    }
    return $document;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    // @todo Update in https://www.drupal.org/node/2300677.
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    return "The 'administer node display' permission is required.";
  }

  /**
   * {@inheritdoc}
   */
  public function testGetIndividual() {
    // @todo Remove when JSON API requires Drupal 8.5 or newer.
    // @see https://www.drupal.org/project/drupal/issues/2866666
    if (floatval(\Drupal::VERSION) < 8.5) {
      $this->markTestSkipped('EntityViewisplay entities had a dysfunctional access control handler until 8.5, this is necessary for this test coverage to work.');
    }
    return parent::testGetIndividual();
  }

  /**
   * {@inheritdoc}
   */
  public function testCollection() {
    // @todo Remove when JSON API requires Drupal 8.5 or newer.
    // @see https://www.drupal.org/project/drupal/issues/2866666
    if (floatval(\Drupal::VERSION) < 8.5) {
      $this->markTestSkipped('EntityViewisplay entities had a dysfunctional access control handler until 8.5, this is necessary for this test coverage to work.');
    }
    return parent::testCollection();
  }

  /**
   * {@inheritdoc}
   */
  protected function createAnotherEntity($key) {
    NodeType::create([
      'name' => 'Pachyderms',
      'type' => 'pachyderms',
    ])->save();

    $entity = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'pachyderms',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $entity->save();

    return $entity;
  }

}
