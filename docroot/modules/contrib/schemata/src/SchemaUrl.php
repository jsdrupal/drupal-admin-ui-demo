<?php

namespace Drupal\schemata;

use Drupal\schemata\schema\SchemaInterface;
use Drupal\Core\Url;

/**
 * Provides additional URL factory methods for linking to Schema.
 *
 * If internal methods or properties of the Url class seem valuable, this class
 * could be made a child class. For now the forced isolation is used to keep it
 * clean.
 */
class SchemaUrl {

  /**
   * Generate a URI for the Schema instance.
   *
   * @param string $format
   *   The format or type of schema.
   * @param string $describes
   *   The format being described.
   * @param \Drupal\schemata\schema\SchemaInterface $schema
   *   The schema for which we generate the link.
   *
   * @return \Drupal\Core\Url
   *   The schema resource Url object.
   */
  public static function fromSchema($format, $describes, SchemaInterface $schema) {
    return static::fromOptions(
      $format,
      $describes,
      $schema->getEntityTypeId(),
      $schema->getBundleId()
    );
  }

  /**
   * Build a URI to a schema resource.
   *
   * @param string $format
   *   The format or type of schema.
   * @param string $describes
   *   The format being described.
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   *
   * @return \Drupal\Core\Url
   *   The schema resource Url object.
   */
  public static function fromOptions($format, $describes, $entity_type_id, $bundle = NULL) {
    $route_name = empty($bundle)
      ? sprintf('schemata.%s', $entity_type_id)
      : sprintf('schemata.%s:%s', $entity_type_id, $bundle);

    return Url::fromRoute($route_name, [], [
      'query' => [
        '_format' => $format,
        '_describes' => $describes,
      ],
      'absolute' => TRUE,
    ]);
  }

}
