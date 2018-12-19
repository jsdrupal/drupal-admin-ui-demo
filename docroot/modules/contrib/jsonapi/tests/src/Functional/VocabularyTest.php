<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * JSON API integration test for the "vocabulary" config entity type.
 *
 * @group jsonapi
 */
class VocabularyTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'taxonomy_vocabulary';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'taxonomy_vocabulary--taxonomy_vocabulary';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer taxonomy']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $vocabulary = Vocabulary::create([
      'name' => 'Llama',
      'vid' => 'llama',
    ]);
    $vocabulary->save();

    return $vocabulary;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/taxonomy_vocabulary/taxonomy_vocabulary/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
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
        'type' => 'taxonomy_vocabulary--taxonomy_vocabulary',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'uuid' => $this->entity->uuid(),
          'vid' => 'llama',
          'langcode' => 'en',
          'status' => TRUE,
          'dependencies' => [],
          'name' => 'Llama',
          'description' => NULL,
          'hierarchy' => 0,
          'weight' => 0,
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

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    if ($method === 'GET') {
      // @todo Remove this when JSON API requires Drupal 8.5 or newer.
      if (floatval(\Drupal::VERSION) < 8.5) {
        return parent::getExpectedUnauthorizedAccessMessage($method);
      }
      return "The following permissions are required: 'access taxonomy overview' OR 'administer taxonomy'.";
    }
    return parent::getExpectedUnauthorizedAccessMessage($method);
  }

}
