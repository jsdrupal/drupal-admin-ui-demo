<?php

namespace Drupal\schemata_json_schema\Normalizer\jsonapi;

use Drupal\schemata_json_schema\Normalizer\json\DataDefinitionNormalizer as JsonDataDefinitionNormalizer;

/**
 * Normalizer for DataDefinitionInterface instances.
 *
 * DataDefinitionInterface is the ultimate parent to all data definitions. This
 * service must always be low priority for data definitions, otherwise the
 * simpler normalization process it supports will take precedence over all the
 * complexities most entity properties contain before reaching this level.
 *
 * DataDefinitionNormalizer produces scalar value definitions.
 *
 * Unlike the other Normalizer services in the JSON Schema module, this one is
 * used by the hal_schemata normalizer. It is unlikely divergent requirements
 * will develop.
 *
 * All the TypedData normalizers extend from this class.
 */
class DataDefinitionNormalizer extends JsonDataDefinitionNormalizer {

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
  protected $describedFormat = 'api_json';

}
