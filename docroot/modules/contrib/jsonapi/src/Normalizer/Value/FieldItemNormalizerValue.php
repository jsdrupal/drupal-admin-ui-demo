<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\jsonapi\Normalizer\CacheableDependencyTrait;

/**
 * Helps normalize field items in compliance with the JSON API spec.
 *
 * @internal
 */
class FieldItemNormalizerValue implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  /**
   * Raw values.
   *
   * @var array
   */
  protected $raw;

  /**
   * Instantiate a FieldItemNormalizerValue object.
   *
   * @param array $values
   *   The normalized result.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $values_cacheability
   *   The cacheability of the normalized result. This cacheability is not part
   *   of $values because field items are normalized by Drupal core's
   *   serialization system, which was never designed with cacheability in mind.
   *   FieldItemNormalizer::normalize() must catch the out-of-band bubbled
   *   cacheability and then passes it to this value object.
   *
   * @see \Drupal\jsonapi\Normalizer\FieldItemNormalizer::normalize()
   */
  public function __construct(array $values, CacheableDependencyInterface $values_cacheability) {
    $this->raw = $values;
    $this->setCacheability($values_cacheability);
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    // If there is only one property, then output it directly.
    $value = count($this->raw) == 1 ? reset($this->raw) : $this->raw;

    return $this->rasterizeValueRecursive($value);
  }

  /**
   * Rasterizes a value recursively.
   *
   * This is mainly for configuration entities where a field can be a tree of
   * values to rasterize.
   *
   * @param mixed $value
   *   Either a scalar, an array or a rasterizable object.
   *
   * @return mixed
   *   The rasterized value.
   */
  protected function rasterizeValueRecursive($value) {
    if (!$value || is_scalar($value)) {
      return $value;
    }
    if (is_array($value)) {
      $output = [];
      foreach ($value as $key => $item) {
        $output[$key] = $this->rasterizeValueRecursive($item);
      }

      return $output;
    }
    if ($value instanceof ValueExtractorInterface) {
      return $value->rasterizeValue();
    }
    // If the object can be turned into a string it's better than nothing.
    if (method_exists($value, '__toString')) {
      return $value->__toString();
    }

    // We give up, since we do not know how to rasterize this.
    return NULL;
  }

}
