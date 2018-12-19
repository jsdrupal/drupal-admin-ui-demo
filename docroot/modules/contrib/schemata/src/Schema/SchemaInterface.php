<?php

namespace Drupal\schemata\Schema;

/**
 * Requirements for a Schema to interact with Schema utilities and serializers.
 *
 * A schema is not directly usable on it's own, it is expected a serializer
 * implemented to process an instance of SchemaInterface will produce a schema
 * to a particular standard, such as JSON-Schema.
 *
 * At present, this interface is only useful for modules that largely sidestep
 * the Schemata module but still choose to leverage Schema-centric serializers.
 */
interface SchemaInterface {

  /**
   * Add additional data properties to the Schema.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   The property data definitions.
   */
  public function addProperties(array $properties);

  /**
   * Retrieve the Entity Type ID.
   *
   * @return string
   *   The Entity Type ID
   */
  public function getEntityTypeId();

  /**
   * Retrieve the Bundle ID.
   *
   * @return string
   *   The Bundle ID
   */
  public function getBundleId();

  /**
   * Retrieve the Schema properties.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The property data definitions.
   */
  public function getProperties();

  /**
   * Retrieve the Schema metadata.
   *
   * @return string[]
   *   The metadata values.
   */
  public function getMetadata();

}
