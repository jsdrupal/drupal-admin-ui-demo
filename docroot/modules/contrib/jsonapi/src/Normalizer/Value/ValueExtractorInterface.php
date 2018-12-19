<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Interface for value objects used in the JSON API normalization process.
 *
 * @internal
 */
interface ValueExtractorInterface {

  /**
   * Get the rasterized value.
   *
   * @return mixed
   *   The value.
   */
  public function rasterizeValue();

  /**
   * Get the includes.
   *
   * @return array[]
   *   An array of includes keyed by entity type and id pair.
   */
  public function rasterizeIncludes();

}
