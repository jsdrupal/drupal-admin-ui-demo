<?php

namespace Drupal\jsonapi\Query;

/**
 * Gathers information about the sort parameter.
 *
 * @internal
 */
class Sort {

  /**
   * The JSON API sort key name.
   *
   * @var string
   */
  const KEY_NAME = 'sort';

  /**
   * The field key in the sort parameter: sort[lorem][<field>].
   *
   * @var string
   */
  const PATH_KEY = 'path';

  /**
   * The direction key in the sort parameter: sort[lorem][<direction>].
   *
   * @var string
   */
  const DIRECTION_KEY = 'direction';

  /**
   * The langcode key in the sort parameter: sort[lorem][<langcode>].
   *
   * @var string
   */
  const LANGUAGE_KEY = 'langcode';

  /**
   * The fields on which to sort.
   *
   * @var string
   */
  protected $fields;

  /**
   * Constructs a new Sort object.
   *
   * Takes an array of sort fields. Example:
   *   [
   *     [
   *       'path' => 'changed',
   *       'direction' => 'DESC',
   *     ],
   *     [
   *       'path' => 'title',
   *       'direction' => 'ASC',
   *       'langcode' => 'en-US',
   *     ],
   *   ]
   *
   * @param array $fields
   *   The the entity query sort fields.
   */
  public function __construct(array $fields) {
    $this->fields = $fields;
  }

  /**
   * Gets the root condition group.
   */
  public function fields() {
    return $this->fields;
  }

}
