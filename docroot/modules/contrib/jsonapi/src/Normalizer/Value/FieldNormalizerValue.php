<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\jsonapi\Normalizer\CacheableDependencyTrait;

/**
 * Helps normalize fields in compliance with the JSON API spec.
 *
 * @internal
 */
class FieldNormalizerValue implements FieldNormalizerValueInterface {

  use CacheableDependencyTrait;
  use CacheableDependenciesMergerTrait;

  /**
   * The values.
   *
   * @var array
   */
  protected $values;

  /**
   * The includes.
   *
   * @var array
   */
  protected $includes;

  /**
   * The field cardinality.
   *
   * @var int
   */
  protected $cardinality;

  /**
   * The property type. Either: 'attributes' or `relationships'.
   *
   * @var string
   */
  protected $propertyType;

  /**
   * Instantiate a FieldNormalizerValue object.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $field_access_result
   *   The field access result.
   * @param \Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue[] $values
   *   The normalized result.
   * @param int $cardinality
   *   The cardinality of the field list.
   * @param string $property_type
   *   The property type of the field: 'attributes' or 'relationships'.
   */
  public function __construct(AccessResultInterface $field_access_result, array $values, $cardinality, $property_type) {
    assert($property_type === 'attributes' || $property_type === 'relationships');
    $this->setCacheability(static::mergeCacheableDependencies(array_merge([$field_access_result], $values)));

    $this->values = $values;
    $this->includes = array_map(function ($value) {
      if (!$value instanceof RelationshipItemNormalizerValue) {
        return NULL;
      }
      return $value->getInclude();
    }, $values);
    $this->includes = array_filter($this->includes);
    $this->cardinality = $cardinality;
    $this->propertyType = $property_type;
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    if (empty($this->values)) {
      return NULL;
    }

    if ($this->cardinality == 1) {
      assert(count($this->values) === 1);
      return $this->values[0] instanceof FieldItemNormalizerValue
        ? $this->values[0]->rasterizeValue() : NULL;
    }

    return array_map(function ($value) {
      return $value instanceof FieldItemNormalizerValue ? $value->rasterizeValue() : NULL;
    }, $this->values);
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeIncludes() {
    return array_map(function ($include) {
      return $include->rasterizeValue();
    }, $this->includes);
  }

  /**
   * {@inheritdoc}
   */
  public function getIncludes() {
    return $this->includes;
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
  public function getAllIncludes() {
    $nested_includes = array_map(function ($include) {
      return $include->getIncludes();
    }, $this->getIncludes());
    $includes = array_reduce(array_filter($nested_includes), function ($carry, $item) {
      return array_merge($carry, $item);
    }, $this->getIncludes());
    // Make sure we don't output duplicate includes.
    return array_values(array_reduce($includes, function ($unique_includes, $include) {
      $rasterized_include = $include->rasterizeValue();
      $unique_includes[$rasterized_include['data']['type'] . ':' . $rasterized_include['data']['id']] = $include;
      return $unique_includes;
    }, []));
  }

}
