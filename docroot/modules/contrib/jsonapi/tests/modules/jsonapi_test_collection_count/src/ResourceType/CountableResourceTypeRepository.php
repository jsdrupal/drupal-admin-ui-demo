<?php

namespace Drupal\jsonapi_test_collection_count\ResourceType;

use Drupal\jsonapi\ResourceType\ResourceTypeRepository;

/**
 * Provides a repository of JSON API configurable resource types.
 */
class CountableResourceTypeRepository extends ResourceTypeRepository {

  /**
   * {@inheritdoc}
   */
  const RESOURCE_TYPE_CLASS = CountableResourceType::class;

}
