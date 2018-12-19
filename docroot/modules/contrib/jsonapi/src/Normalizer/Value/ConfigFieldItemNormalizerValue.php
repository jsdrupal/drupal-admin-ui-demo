<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Helps normalize config entity "fields" in compliance with the JSON API spec.
 *
 * @internal
 */
class ConfigFieldItemNormalizerValue extends FieldItemNormalizerValue {

  /**
   * {@inheritdoc}
   *
   * @var mixed
   */
  protected $raw;

  /**
   * Instantiate a ConfigFieldItemNormalizerValue object.
   *
   * @param mixed $values
   *   The normalized result.
   */
  public function __construct($values) {
    $this->raw = $values;
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    return $this->rasterizeValueRecursive($this->raw);
  }

}
