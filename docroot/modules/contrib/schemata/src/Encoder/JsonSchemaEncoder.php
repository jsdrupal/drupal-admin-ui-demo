<?php

namespace Drupal\schemata\Encoder;

use Symfony\Component\Serializer\Encoder\JsonEncoder as SymfonyJsonEncoder;

/**
 * Encodes JSON API data.
 *
 * Simply respond to application/vnd.api+json format requests using encoder.
 */
class JsonSchemaEncoder extends SymfonyJsonEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected $format = 'schema_json';

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {
    list($data_format,) = explode(':', $format);
    return $data_format == $this->format;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {
    return FALSE;
  }

}
