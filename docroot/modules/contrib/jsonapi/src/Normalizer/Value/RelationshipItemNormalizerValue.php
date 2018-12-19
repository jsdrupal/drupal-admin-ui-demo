<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Helps normalize relationship items in compliance with the JSON API spec.
 *
 * @internal
 */
class RelationshipItemNormalizerValue extends FieldItemNormalizerValue implements ValueExtractorInterface, CacheableDependencyInterface {

  use CacheableDependenciesMergerTrait;

  /**
   * Resource path.
   *
   * @var string
   */
  protected $resource;

  /**
   * Included normalized entity, if any.
   *
   * @var \Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue|\Drupal\jsonapi\Normalizer\Value\JsonApiDocumentTopLevelNormalizerValue|\Drupal\jsonapi\Normalizer\Value\HttpExceptionNormalizerValue|null
   */
  protected $include;

  /**
   * Instantiates a RelationshipItemNormalizerValue object.
   *
   * @param array $values
   *   The values.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $values_cacheability
   *   The cacheability of the normalized result. This cacheability is not part
   *   of $values because field items are normalized by Drupal core's
   *   serialization system, which was never designed with cacheability in mind.
   *   FieldItemNormalizer::normalize() must catch the out-of-band bubbled
   *   cacheability and then passes it to this value object.
   * @param string $resource
   *   The resource type of the target entity.
   * @param \Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue|\Drupal\jsonapi\Normalizer\Value\JsonApiDocumentTopLevelNormalizerValue|\Drupal\jsonapi\Normalizer\Value\HttpExceptionNormalizerValue|null $include
   *   The included normalized entity, or NULL.
   */
  public function __construct(array $values, CacheableDependencyInterface $values_cacheability, $resource, $include) {
    assert($include === NULL || $include instanceof EntityNormalizerValue || $include instanceof JsonApiDocumentTopLevelNormalizerValue || $include instanceof HttpExceptionNormalizerValue);
    parent::__construct($values, $values_cacheability);
    if ($include !== NULL) {
      $this->setCacheability(static::mergeCacheableDependencies([$include, $values_cacheability]));
    }
    $this->resource = $resource;
    $this->include = $include;
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    if (!$value = parent::rasterizeValue()) {
      return $value;
    }
    $rasterized_value = [
      'type' => $this->resource->getTypeName(),
      'id' => empty($value['target_uuid']) ? $value : $value['target_uuid'],
    ];

    if (!empty($value['meta'])) {
      $rasterized_value['meta'] = $value['meta'];
    }

    return $rasterized_value;
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeIncludes() {
    return $this->include->rasterizeValue();
  }

  /**
   * Gets the include.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue
   *   The include.
   */
  public function getInclude() {
    return $this->include;
  }

}
