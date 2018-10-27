<?php

namespace Drupal\schemata_json_schema\Plugin\schemata_json_schema\type_mapper;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Converts Data Definition properties of the datetime_iso8601 to JSON Schema.
 *
 * @TypeMapper(
 *  id = "datetime_iso8601"
 * )
 */
class DateTime8601TypeMapper extends TypeMapperBase {

  /**
   * {@inheritdoc}
   */
  public function getMappedValue(DataDefinitionInterface $property) {
    $value = parent::getMappedValue($property);
    $value['type'] = 'string';
    $value['format'] = 'date';
    return $value;
  }

}
