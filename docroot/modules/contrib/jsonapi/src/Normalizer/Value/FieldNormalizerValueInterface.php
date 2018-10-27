<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Interface to help normalize fields in compliance with the JSON API spec.
 *
 * @internal
 */
interface FieldNormalizerValueInterface extends ValueExtractorInterface, CacheableDependencyInterface {

  /**
   * Gets the includes.
   *
   * @return mixed
   *   The includes.
   */
  public function getIncludes();

  /**
   * Gets the propertyType.
   *
   * @return mixed
   *   The propertyType.
   */
  public function getPropertyType();

  /**
   * Computes all the nested includes recursively.
   *
   * @return array
   *   The includes and the nested includes.
   */
  public function getAllIncludes();

}
