<?php

namespace Drupal\jsonapi_test_data_type\Normalizer;

use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Normalizes string data, with a twist: it replaces 'super' with 'NOT'.
 */
class StringNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = StringData::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    return str_replace('super', 'NOT', $object->getValue());
  }

}
