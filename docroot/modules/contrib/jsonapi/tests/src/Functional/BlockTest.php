<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\block\Entity\Block;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * JSON API integration test for the "Block" config entity type.
 *
 * @group jsonapi
 */
class BlockTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'block';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'block--block';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\block\BlockInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->entity->setVisibilityConfig('user_role', [])->save();
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $block = Block::create([
      'plugin' => 'llama_block',
      'region' => 'header',
      'id' => 'llama',
      'theme' => 'classy',
    ]);
    // All blocks can be viewed by the anonymous user by default. An interesting
    // side effect of this is that any anonymous user is also able to read the
    // corresponding block config entity via REST, even if an authentication
    // provider is configured for the block config entity REST resource! In
    // other words: Block entities do not distinguish between 'view' as in
    // "render on a page" and 'view' as in "read the configuration".
    // This prevents that.
    // @todo Fix this in https://www.drupal.org/node/2820315.
    $block->setVisibilityConfig('user_role', [
      'id' => 'user_role',
      'roles' => ['non-existing-role' => 'non-existing-role'],
      'negate' => FALSE,
      'context_mapping' => [
        'user' => '@user.current_user_context:current_user',
      ],
    ]);
    $block->save();

    return $block;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/block/block/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
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
        'type' => 'block--block',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'id' => 'llama',
          'weight' => NULL,
          'langcode' => 'en',
          'status' => TRUE,
          'dependencies' => [
            'theme' => [
              'classy',
            ],
          ],
          'theme' => 'classy',
          'region' => 'header',
          'provider' => NULL,
          'plugin' => 'llama_block',
          'settings' => [
            'id' => 'broken',
            'label' => '',
            'provider' => 'core',
            'label_display' => 'visible',
          ],
          'visibility' => [],
          'uuid' => $this->entity->uuid(),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    // @todo Update once https://www.drupal.org/node/2300677 is fixed.
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts(array $sparse_fieldset = NULL) {
    // @see ::createEntity()
    return array_values(array_diff(parent::getExpectedCacheContexts(), ['user.permissions']));
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheTags(array $sparse_fieldset = NULL) {
    // Because the 'user.permissions' cache context is missing, the cache tag
    // for the anonymous user role is never added automatically.
    return array_values(array_diff(parent::getExpectedCacheTags(), ['config:user.role.anonymous']));
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET':
        return '';

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessCacheability() {
    // @see \Drupal\block\BlockAccessControlHandler::checkAccess()
    return parent::getExpectedUnauthorizedAccessCacheability()
      ->setCacheTags([
        '4xx-response',
        'config:block.block.llama',
        'http_response',
        'user:2',
      ])
      ->setCacheContexts(['user.roles']);
  }

  /**
   * {@inheritdoc}
   */
  protected static function getExpectedCollectionCacheability(array $collection, array $sparse_fieldset = NULL, AccountInterface $account) {
    return parent::getExpectedCollectionCacheability($collection, $sparse_fieldset, $account)
      ->addCacheTags(['user:2'])
      ->addCacheContexts(['user.roles']);
  }

}
