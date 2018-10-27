<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\rest\Functional\BcTimestampNormalizerUnixTestTrait;
use Drupal\user\Entity\User;

/**
 * JSON API integration test for the "EntityTest" content entity type.
 *
 * @group jsonapi
 */
class EntityTestTest extends ResourceTestBase {

  use BcTimestampNormalizerUnixTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['entity_test'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'entity_test';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'entity_test--entity_test';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [];

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['view test entity']);
        break;

      case 'POST':
        $this->grantPermissionsToTestedRole(['create entity_test entity_test_with_bundle entities']);
        break;

      case 'PATCH':
      case 'DELETE':
        $this->grantPermissionsToTestedRole(['administer entity_test content']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Set flag so that internal field 'internal_string_field' is created.
    // @see entity_test_entity_base_field_info()
    $this->container->get('state')->set('entity_test.internal_field', TRUE);
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();

    $entity_test = EntityTest::create([
      'name' => 'Llama',
      'type' => 'entity_test',
      // Set a value for the internal field to confirm that it will not be
      // returned in normalization.
      // @see entity_test_entity_base_field_info().
      'internal_string_field' => [
        'value' => 'This value shall not be internal!',
      ],
    ]);
    $entity_test->setOwnerId(0);
    $entity_test->save();

    return $entity_test;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/entity_test/entity_test/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    $author = User::load(0);
    $normalization = [
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
        'type' => 'entity_test--entity_test',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'id' => 1,
          'created' => (int) $this->entity->get('created')->value,
          // @todo uncomment this in https://www.drupal.org/project/jsonapi/issues/2929932
          /* 'created' => $this->formatExpectedTimestampItemValues((int) $this->entity->get('created')->value), */
          'field_test_text' => NULL,
          'langcode' => 'en',
          'name' => 'Llama',
          'type' => 'entity_test',
          'uuid' => $this->entity->uuid(),
        ],
        'relationships' => [
          'user_id' => [
            'data' => [
              'id' => $author->uuid(),
              'type' => 'user--user',
            ],
            'links' => [
              'related' => $self_url . '/user_id',
              'self' => $self_url . '/relationships/user_id',
            ],
          ],
        ],
      ],
    ];
    // @todo Remove this modification when JSON API requires Drupal 8.5 or newer, and do an early return above instead.
    if (floatval(\Drupal::VERSION) < 8.5) {
      unset($normalization['data']['attributes']['internal_string_field']);
    }
    return $normalization;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    return [
      'data' => [
        'type' => 'entity_test--entity_test',
        'attributes' => [
          'name' => 'Dramallama',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET':
        return "The 'view test entity' permission is required.";

      case 'POST':
        return "The following permissions are required: 'administer entity_test content' OR 'administer entity_test_with_bundle content' OR 'create entity_test entity_test_with_bundle entities'.";

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getSparseFieldSets() {
    // EntityTest's owner field name is `user_id`, not `uid`, which breaks
    // nested sparse fieldset tests.
    return array_diff_key(parent::getSparseFieldSets(), array_flip([
      'nested_empty_fieldset',
      'nested_fieldset_with_owner_fieldset',
    ]));
  }

}
