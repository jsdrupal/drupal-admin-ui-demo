<?php

namespace Drupal\schemata_json_schema\Plugin\schemata_json_schema\type_mapper;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Converts Data Definition properties of filter_format type to JSON Schema.
 *
 * @TypeMapper(
 *  id = "filter_format"
 * )
 */
class FilterFormatTypeMapper extends TypeMapperBase {

  /**
   * {@inheritdoc}
   */
  public function getMappedValue(DataDefinitionInterface $property) {
    $value = parent::getMappedValue($property);
    $value['type'] = 'string';
    return $value;
  }

}
