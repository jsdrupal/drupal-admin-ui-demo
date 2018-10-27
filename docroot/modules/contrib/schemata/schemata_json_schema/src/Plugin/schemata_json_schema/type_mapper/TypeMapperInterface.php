<?php

namespace Drupal\schemata_json_schema\Plugin\schemata_json_schema\type_mapper;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Defines the extended methods needed for a TypeMapper plugin.
 */
interface TypeMapperInterface {

  /**
   * Convert the data definition property to a JSON Schema form.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property
   *   The data definition property.
   *
   * @return mixed
   *   The mapped value to represent the property in a JSON Schema schema.
   */
  public function getMappedValue(DataDefinitionInterface $property);

}
