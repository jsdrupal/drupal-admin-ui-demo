<?php

namespace Drupal\schemata_json_schema\Plugin\schemata_json_schema\type_mapper;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Converts Data Definition properties of timestamp type to JSON Schema.
 *
 * @TypeMapper(
 *  id = "timestamp"
 * )
 */
class TimestampTypeMapper extends TypeMapperBase {

  /**
   * {@inheritdoc}
   */
  public function getMappedValue(DataDefinitionInterface $property) {
    $value = parent::getMappedValue($property);
    $value['type'] = 'number';
    $value['format'] = 'utc-millisec';
    return $value;
  }

}
