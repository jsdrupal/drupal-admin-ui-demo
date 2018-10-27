<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Core\Url;
use Drupal\shortcut\Entity\Shortcut;
use Drupal\shortcut\Entity\ShortcutSet;

/**
 * JSON API integration test for the "Shortcut" content entity type.
 *
 * @group jsonapi
 */
class ShortcutTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['comment', 'shortcut'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'shortcut';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'shortcut--default';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\shortcut\ShortcutInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [];

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['access shortcuts', 'customize shortcut links']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $shortcut = Shortcut::create([
      'shortcut_set' => 'default',
      'title' => t('Comments'),
      'weight' => -20,
      'link' => [
        'uri' => 'internal:/user/logout',
      ],
    ]);
    $shortcut->save();

    return $shortcut;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/shortcut/default/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
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
        'type' => 'shortcut--default',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'uuid' => $this->entity->uuid(),
          'id' => (int) $this->entity->id(),
          'title' => 'Comments',
          'link' => [
            'uri' => 'internal:/user/logout',
            'title' => NULL,
            'options' => [],
          ],
          'langcode' => 'en',
          'default_langcode' => TRUE,
          'weight' => -20,
        ],
        'relationships' => [
          'shortcut_set' => [
            'data' => [
              'type' => 'shortcut_set--shortcut_set',
              'id' => ShortcutSet::load('default')->uuid(),
            ],
            'links' => [
              'related' => $self_url . '/shortcut_set',
              'self' => $self_url . '/relationships/shortcut_set',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    return [
      'data' => [
        'type' => 'shortcut--default',
        'attributes' => [
          'title' => 'Comments',
          'link' => [
            'uri' => 'internal:/',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    return "The shortcut set must be the currently displayed set for the user and the user must have 'access shortcuts' AND 'customize shortcut links' permissions.";
  }

  /**
   * {@inheritdoc}
   */
  public function testPostIndividual() {
    $this->markTestSkipped('Disabled until https://www.drupal.org/project/drupal/issues/2982060 is fixed.');
  }

  /**
   * {@inheritdoc}
   */
  public function testRelationships() {
    $this->markTestSkipped('Disabled until https://www.drupal.org/project/drupal/issues/2982060 is fixed.');
  }

  /**
   * {@inheritdoc}
   */
  public function testPatchIndividual() {
    $this->markTestSkipped('Disabled until https://www.drupal.org/project/drupal/issues/2982060 is fixed.');
  }

}
