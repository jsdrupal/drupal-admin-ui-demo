<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Trait for \Drupal\Core\Cache\CacheableDependencyInterface.
 *
 * @internal
 * @deprecated Remove when JSON API requires Drupal 8.5 or newer, update all users to \Drupal\Core\Cache\CacheableDependencyTrait instead.
 */
trait CacheableDependencyTrait {

  /**
   * Cache contexts.
   *
   * @var string[]
   */
  protected $cacheContexts = [];

  /**
   * Cache tags.
   *
   * @var string[]
   */
  protected $cacheTags = [];

  /**
   * Cache max-age.
   *
   * @var int
   */
  protected $cacheMaxAge = Cache::PERMANENT;

  /**
   * Sets cacheability; useful for value object constructors.
   *
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $cacheability
   *   The cacheability to set.
   *
   * @return $this
   */
  protected function setCacheability(CacheableDependencyInterface $cacheability) {
    $this->cacheContexts = $cacheability->getCacheContexts();
    $this->cacheTags = $cacheability->getCacheTags();
    $this->cacheMaxAge = $cacheability->getCacheMaxAge();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->cacheTags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->cacheContexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->cacheMaxAge;
  }

}
