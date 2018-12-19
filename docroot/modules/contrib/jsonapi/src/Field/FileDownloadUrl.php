<?php

namespace Drupal\jsonapi\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Extends core URL field functionality.
 *
 * @internal
 */
class FileDownloadUrl extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * Creates a relative URL out of a URI.
   *
   * This is a wrapper to the procedural code for testing purposes. For obvious
   * reasons this method will not be unit tested, but that is fine since it's
   * only using already tested Drupal API functions.
   *
   * @param string $uri
   *   The URI to transform.
   *
   * @return string
   *   The transformed relative URL.
   */
  protected function fileCreateRootRelativeUrl($uri) {
    $wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
    if ($wrapper && ($wrapper->getType() & StreamWrapperInterface::VISIBLE)) {
      return file_url_transform_relative(file_create_url($uri));
    }

    // For testing purposes, return the $uri when the scheme is not a wrapper or
    // not visible.
    return $uri;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $this->getEntity()
      ->get('uri')
      ->access($operation, $account, $return_as_object);
  }

  /**
   * Initialize the internal field list with the modified items.
   */
  protected function computeValue() {
    $url_list = [];
    foreach ($this->getEntity()->get('uri') as $delta => $uri_item) {
      $path = $this->fileCreateRootRelativeUrl($uri_item->value);
      $url_list[$delta] = $this->createItem($delta, $path);
    }
    $this->list = $url_list;
  }

}
