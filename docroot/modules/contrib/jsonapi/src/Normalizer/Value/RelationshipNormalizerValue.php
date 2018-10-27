<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Access\AccessResultInterface;

/**
 * Helps normalize relationships in compliance with the JSON API spec.
 *
 * @internal
 */
class RelationshipNormalizerValue extends FieldNormalizerValue {

  /**
   * The link manager.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * The JSON API resource type.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceType
   */
  protected $resourceType;

  /**
   * The field name for the link generation.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The entity ID for the host entity.
   *
   * @var string
   */
  protected $hostEntityId;

  /**
   * Instantiate a EntityReferenceNormalizerValue object.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $relationship_access_result
   *   The relationship access result.
   * @param RelationshipItemNormalizerValue[] $values
   *   The normalized result.
   * @param int $cardinality
   *   The number of fields for the field list.
   * @param array $link_context
   *   All the objects and variables needed to generate the links for this
   *   relationship.
   */
  public function __construct(AccessResultInterface $relationship_access_result, array $values, $cardinality, array $link_context) {
    $this->hostEntityId = $link_context['host_entity_id'];
    $this->fieldName = $link_context['field_name'];
    $this->linkManager = $link_context['link_manager'];
    $this->resourceType = $link_context['resource_type'];
    array_walk($values, function ($field_item_value) {
      if (!$field_item_value instanceof RelationshipItemNormalizerValue) {
        throw new \RuntimeException(sprintf('Unexpected normalizer item value for this %s.', get_called_class()));
      }
    });
    parent::__construct($relationship_access_result, $values, $cardinality, 'relationships');
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    $links = $this->getLinks($this->fieldName);
    // Empty 'to-one' relationships must be NULL.
    // Empty 'to-many' relationships must be an empty array.
    // @link http://jsonapi.org/format/#document-resource-object-linkage
    $data = parent::rasterizeValue() ?: [];

    if ($this->cardinality === 1) {
      return empty($data)
        ? ['data' => NULL, 'links' => $links]
        : ['data' => $data, 'links' => $links];
    }
    else {
      return ['data' => static::ensureUniqueResourceIdentifierObjects($data), 'links' => $links];
    }
  }

  /**
   * Ensures each resource identifier object is unique.
   *
   * The official JSON API JSON-Schema document requires that no two resource
   * identifier objects are duplicated.
   *
   * This adds an @code arity @endcode member to each object's
   * @code meta @endcode member. The value of this member is an integer that is
   * incremented by 1 (starting from 0) for each repeated resource identifier
   * sharing a common @code type @endcode and @code id @endcode.
   *
   * @param array $resource_identifier_objects
   *   A list of JSON API resource identifier objects.
   *
   * @return array
   *   A set of JSON API resource identifier objects, with those having multiple
   *   occurrences getting [meta][arity].
   *
   * @see http://jsonapi.org/format/#document-resource-object-relationships
   * @see https://github.com/json-api/json-api/pull/1156#issuecomment-325377995
   * @see https://www.drupal.org/project/jsonapi/issues/2864680
   */
  protected static function ensureUniqueResourceIdentifierObjects(array $resource_identifier_objects) {
    if (count($resource_identifier_objects) <= 1) {
      return $resource_identifier_objects;
    }

    // Count each repeated resource identifier and track their array indices.
    $analysis = [];
    foreach ($resource_identifier_objects as $index => $rio) {
      $composite_key = $rio['type'] . ':' . $rio['id'];

      $analysis[$composite_key]['count'] = isset($analysis[$composite_key])
        ? $analysis[$composite_key]['count'] + 1
        : 0;

      // The index will later be used to assign an arity to repeated resource
      // identifier objects. Doing this in two phases prevents adding an arity
      // to objects which only occur once.
      $analysis[$composite_key]['indices'][] = $index;
    }

    // Assign an arity to objects whose type + ID pair occurred more than once.
    foreach ($analysis as $computed) {
      if ($computed['count'] > 0) {
        foreach ($computed['indices'] as $arity => $index) {
          $resource_identifier_objects[$index]['meta']['arity'] = $arity;
        }
      }
    }

    return $resource_identifier_objects;
  }

  /**
   * Gets the links for the relationship.
   *
   * @param string $field_name
   *   The field name for the relationship.
   *
   * @return array
   *   An array of links to be rasterized.
   */
  protected function getLinks($field_name) {
    $route_parameters = [
      'related' => $this->resourceType->getPublicName($field_name),
    ];
    $links['self'] = $this->linkManager->getEntityLink(
      $this->hostEntityId,
      $this->resourceType,
      $route_parameters,
      'relationship'
    );
    $resource_types = $this->resourceType->getRelatableResourceTypesByField($field_name);
    if (static::hasNonInternalResourceType($resource_types)) {
      $links['related'] = $this->linkManager->getEntityLink(
        $this->hostEntityId,
        $this->resourceType,
        $route_parameters,
        'related'
      );
    }
    return $links;
  }

  /**
   * Determines if a given list of resource types contains a non-internal type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType[] $resource_types
   *   The JSON API resource types to evaluate.
   *
   * @return bool
   *   FALSE if every resource type is internal, TRUE otherwise.
   */
  protected static function hasNonInternalResourceType(array $resource_types) {
    foreach ($resource_types as $resource_type) {
      if (!$resource_type->isInternal()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
