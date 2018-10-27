<?php

namespace Drupal\schemata_json_schema\Plugin\schemata_json_schema\type_mapper;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Converts Data Definition properties of entity_reference type to JSON Schema.
 *
 * @TypeMapper(
 *  id = "entity_reference"
 * )
 */
class EntityReferenceTypeMapper extends TypeMapperBase {

  /**
   * {@inheritdoc}
   */
  public function getMappedValue(DataDefinitionInterface $property) {
    $value = parent::getMappedValue($property);
    $value['type'] = 'object';
    return $value;
  }

}
