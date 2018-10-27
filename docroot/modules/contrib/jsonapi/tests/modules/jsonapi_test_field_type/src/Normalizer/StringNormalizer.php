<?php

namespace Drupal\jsonapi_test_field_type\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\serialization\Normalizer\FieldItemNormalizer;

/**
 * Normalizes string fields, with a twist: it replaces 'super' with 'NOT'.
 */
class StringNormalizer extends FieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = StringItem::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $data = parent::normalize($object, $format, $context);
    $data['value'] = str_replace('super', 'NOT', $data['value']);
    return $data;
  }

}
