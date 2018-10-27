<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\config_test\Entity\ConfigTest;
use Drupal\Core\Url;

/**
 * JSON API integration test for the "ConfigTest" config entity type.
 *
 * @group jsonapi
 */
class ConfigTestTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['config_test', 'config_test_rest'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'config_test';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'config_test--config_test';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\config_test\ConfigTestInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['view config_test']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $config_test = ConfigTest::create([
      'id' => 'llama',
      'label' => 'Llama',
    ]);
    $config_test->save();

    return $config_test;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/config_test/config_test/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
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
        'type' => 'config_test--config_test',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'uuid' => $this->entity->uuid(),
          'id' => 'llama',
          'weight' => 0,
          'langcode' => 'en',
          'status' => TRUE,
          'dependencies' => [],
          'label' => 'Llama',
          'style' => NULL,
          'size' => NULL,
          'size_value' => NULL,
          'protected_property' => NULL,
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
