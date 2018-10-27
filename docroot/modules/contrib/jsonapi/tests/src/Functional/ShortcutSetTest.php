<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Core\Url;
use Drupal\shortcut\Entity\ShortcutSet;

/**
 * JSON API integration test for the "ShortcutSet" config entity type.
 *
 * @group jsonapi
 */
class ShortcutSetTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['shortcut'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'shortcut_set';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'shortcut_set--shortcut_set';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\shortcut\ShortcutSetInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['access shortcuts']);
        break;

      case 'POST':
      case 'PATCH':
        $this->grantPermissionsToTestedRole(['access shortcuts', 'customize shortcut links']);
        break;

      case 'DELETE':
        $this->grantPermissionsToTestedRole(['administer shortcuts']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $set = ShortcutSet::create([
      'id' => 'llama_set',
      'label' => 'Llama Set',
    ]);
    $set->save();
    return $set;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/shortcut_set/shortcut_set/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    return [
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
        'type' => 'shortcut_set--shortcut_set',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'id' => 'llama_set',
          'uuid' => $this->entity->uuid(),
          'label' => 'Llama Set',
          'status' => TRUE,
          'langcode' => 'en',
          'dependencies' => [],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    // @todo Update in https://www.drupal.org/node/2300677.
  }

}
