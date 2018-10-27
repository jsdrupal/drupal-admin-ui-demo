<?php

namespace Drupal\schemata_json_schema\Normalizer\hal;

use Drupal\schemata_json_schema\Normalizer\json\ListDataDefinitionNormalizer as JsonListDataDefinitionNormalizer;

/**
 * HAL normalizer for ListDataDefinitionInterface objects.
 */
class ListDataDefinitionNormalizer extends JsonListDataDefinitionNormalizer {

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
