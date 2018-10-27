<?php

namespace Drupal\schemata_json_schema\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a JSON Schema Type Mapper annotation object.
 *
 * Plugin Namespace: Plugin\schemata_json_schema\type_mapper.
 *
 * @ingroup third_party
 *
 * @Annotation
 */
class TypeMapper extends Plugin {

  /**
   * The TypeMapper plugin ID.
   *
   * @var string
   */
  public $id;

}
