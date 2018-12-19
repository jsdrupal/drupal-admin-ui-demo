<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Access\AccessResult;

/**
 * Helps normalize relationships in compliance with the JSON API spec.
 *
 * Specifically designed for include-only relationships: when a relationship
 * field is omitted due to a sparse fieldset, yet its related resources are
 * included via `?include`.
 *
 * @internal
 */
class IncludeOnlyRelationshipNormalizerValue extends FieldNormalizerValue {

  /**
   * Instantiate a IncludeOnlyRelationshipNormalizerValue object.
   *
   * @param \Drupal\jsonapi\Normalizer\Value\RelationshipNormalizerValue $relationship_normalizer_value
   *   The relationship normalizer value to convert into an include-only one.
   */
  public function __construct(RelationshipNormalizerValue $relationship_normalizer_value) {
    assert(!empty($relationship_normalizer_value->getIncludes()), sprintf('%s should only be used for relationships that do have includes.', get_called_class()));

    parent::__construct(AccessResult::allowed(), [], NULL, 'relationships');
    $this->includes = $relationship_normalizer_value->getIncludes();
    $this->setCacheability($relationship_normalizer_value);
  }

}
