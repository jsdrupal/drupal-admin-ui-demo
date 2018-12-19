<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\jsonapi\Normalizer\CacheableDependencyTrait;

/**
 * Normalizes null fields in accordance with the JSON API specification.
 *
 * @internal
 */
class NullFieldNormalizerValue implements FieldNormalizerValueInterface {

  use CacheableDependencyTrait;

  /**
   * The property type.
   *
   * @var mixed
   */
  protected $propertyType;

  /**
   * Instantiate a FieldNormalizerValue object.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $field_access_result
   *   The field access result.
   * @param string $property_type
   *   The property type of the field: 'attributes' or 'relationships'.
   */
  public function __construct(AccessResultInterface $field_access_result, $property_type) {
    assert($property_type === 'attributes' || $property_type === 'relationships');
    $this->setCacheability($field_access_result);

    $this->propertyType = $property_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getIncludes() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyType() {
    return $this->propertyType;
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeIncludes() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllIncludes() {
    return NULL;
  }

}
