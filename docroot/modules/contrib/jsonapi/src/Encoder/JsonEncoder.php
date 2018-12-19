<?php

namespace Drupal\jsonapi\Encoder;

use Drupal\jsonapi\Normalizer\Value\ValueExtractorInterface;
use Drupal\serialization\Encoder\JsonEncoder as SerializationJsonEncoder;

/**
 * Encodes JSON API data.
 *
 * @internal
 */
class JsonEncoder extends SerializationJsonEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected static $format = ['api_json'];

  /**
   * {@inheritdoc}
   *
   * @see http://jsonapi.org/format/#errors
   */
  public function encode($data, $format, array $context = []) {
    // Make sure that any auto-normalizable object gets normalized before
    // encoding. This is specially important to generate the errors in partial
    // success responses.
    if ($data instanceof ValueExtractorInterface) {
      $data = $data->rasterizeValue();
    }
    // Allows wrapping the encoded output. This is so we can use the same
    // encoder and normalizers when serializing HttpExceptions to match the
    // JSON API specification.
    if (!empty($context['data_wrapper'])) {
      $data = [$context['data_wrapper'] => $data];
    }
    return parent::encode($data, $format, $context);
  }

}
