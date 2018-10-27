<?php

namespace Drupal\schemata_json_schema\Plugin\schemata_json_schema\type_mapper;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Converts Data Definition properties of the email to JSON Schema.
 *
 * @TypeMapper(
 *  id = "email"
 * )
 */
class EmailTypeMapper extends TypeMapperBase {

  /**
   * {@inheritdoc}
   */
  public function getMappedValue(DataDefinitionInterface $property) {
    $value = parent::getMappedValue($property);
    $value['type'] = 'string';
    $value['format'] = 'email';
    return $value;
  }

}
