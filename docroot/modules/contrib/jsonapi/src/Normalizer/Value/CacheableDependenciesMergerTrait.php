<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Trait for \Drupal\Core\Cache\CacheableDependencyInterface::setCacheability().
 *
 * @internal
 */
trait CacheableDependenciesMergerTrait {

  /**
   * Determines the joint cacheability of all provided dependencies.
   *
   * @param \Drupal\Core\Cache\CacheableDependencyInterface|object[] $dependencies
   *   The dependencies.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheability of all dependencies.
   *
   * @see \Drupal\Core\Cache\RefinableCacheableDependencyInterface::addCacheableDependency()
   */
  protected static function mergeCacheableDependencies(array $dependencies) {
    $merged_cacheability = new CacheableMetadata();
    array_walk($dependencies, function ($dependency) use ($merged_cacheability) {
      $merged_cacheability->addCacheableDependency($dependency);
    });
    return $merged_cacheability;
  }

}
