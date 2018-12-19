<?php

namespace Drupal\schemata_json_schema\Normalizer\hal;

use Drupal\schemata_json_schema\Normalizer\json\FieldDefinitionNormalizer as JsonFieldDefinitionNormalizer;

/**
 * HAL normalizer for FieldDefinition objects.
 */
class FieldDefinitionNormalizer extends JsonFieldDefinitionNormalizer {

  use ReferenceListTrait;

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $format = 'schema_json';

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $describedFormat = 'hal_json';

}
