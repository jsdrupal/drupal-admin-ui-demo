<?php

namespace Drupal\jsonapi\ResourceType;

/**
 * Provides a repository of all JSON API resource types.
 *
 * @internal
 */
interface ResourceTypeRepositoryInterface {

  /**
   * Gets all JSON API resource types.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType[]
   *   The set of all JSON API resource types in this Drupal instance.
   */
  public function all();

  /**
   * Gets a specific JSON API resource type based on entity type ID and bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The id for the bundle to find.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   The requested JSON API resource type, if it exists. NULL otherwise.
   */
  public function get($entity_type_id, $bundle);

  /**
   * Gets a specific JSON API resource type based on a supplied typename.
   *
   * @param string $type_name
   *   The public typename of a JSON API resource.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType|null
   *   The resource type, or NULL if none found.
   */
  public function getByTypeName($type_name);

}
