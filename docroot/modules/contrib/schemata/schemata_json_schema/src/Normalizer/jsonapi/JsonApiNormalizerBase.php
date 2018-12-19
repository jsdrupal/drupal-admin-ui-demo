<?php

namespace Drupal\schemata_json_schema\Normalizer\jsonapi;

use Drupal\schemata\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Base class for JSON Schema Normalizers.
 */
abstract class JsonApiNormalizerBase extends NormalizerBase implements DenormalizerInterface {

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
