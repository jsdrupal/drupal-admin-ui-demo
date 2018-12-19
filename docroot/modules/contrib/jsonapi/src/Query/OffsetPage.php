<?php

namespace Drupal\jsonapi\Query;

/**
 * Value object for containing the requested offset and page parameters.
 *
 * @internal
 */
class OffsetPage {

  /**
   * The JSON API pagination key name.
   *
   * @var string
   */
  const KEY_NAME = 'page';

  /**
   * The offset key in the page parameter: page[offset].
   *
   * @var string
   */
  const OFFSET_KEY = 'offset';

  /**
   * The size key in the page parameter: page[limit].
   *
   * @var string
   */
  const SIZE_KEY = 'limit';

  /**
   * Default offset.
   *
   * @var int
   */
  const DEFAULT_OFFSET = 0;

  /**
   * Max size.
   *
   * @var int
   */
  const SIZE_MAX = 50;

  /**
   * The offset for the query.
   *
   * @var int
   */
  protected $offset;

  /**
   * The size of the query.
   *
   * @var int
   */
  protected $size;

  /**
   * Instantiates an OffsetPage object.
   *
   * @param int $offset
   *   The query offset.
   * @param int $size
   *   The query size limit.
   */
  public function __construct($offset, $size) {
    $this->offset = $offset;
    $this->size = $size;
  }

  /**
   * Returns the current offset.
   *
   * @return int
   *   The query offset.
   */
  public function getOffset() {
    return $this->offset;
  }

  /**
   * Returns the page size.
   *
   * @return int
   *   The requested size of the query result.
   */
  public function getSize() {
    return $this->size;
  }

}
