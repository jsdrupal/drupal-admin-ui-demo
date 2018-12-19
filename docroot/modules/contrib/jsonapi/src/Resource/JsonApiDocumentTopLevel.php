<?php

namespace Drupal\jsonapi\Resource;

/**
 * Represents a JSON API document's "top level".
 *
 * @see http://jsonapi.org/format/#document-top-level
 *
 * @internal
 *
 * @todo Add the missing required members: 'error' and 'meta' or document why not.
 * @todo Add support for the missing optional members: 'jsonapi', 'links' and 'included' or document why not.
 */
class JsonApiDocumentTopLevel {

  /**
   * The data to normalize.
   *
   * @var \Drupal\Core\Entity\EntityInterface|\Drupal\jsonapi\EntityCollection
   */
  protected $data;

  /**
   * Instantiates a JsonApiDocumentTopLevel object.
   *
   * @param \Drupal\Core\Entity\EntityInterface|\Drupal\jsonapi\EntityCollection $data
   *   The data to normalize. It can be either a straight up entity or a
   *   collection of entities.
   */
  public function __construct($data) {
    $this->data = $data;
  }

  /**
   * Gets the data.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\jsonapi\EntityCollection
   *   The data.
   */
  public function getData() {
    return $this->data;
  }

}
