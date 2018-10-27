<?php

namespace Drupal\schemata\Normalizer;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\serialization\Normalizer\NormalizerBase as SerializationNormalizerBase;
use Drupal\Component\Utility\NestedArray;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Base class for JSON Schema Normalizers.
 */
abstract class NormalizerBase extends SerializationNormalizerBase implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    throw new \RuntimeException('Denormalization is not supported.');
  }

  /**
   * Normalize an array of data definitions.
   *
   * This normalization process gets an array of properties and an array of
   * properties that are required by name. This is needed by the
   * SchemataSchemaNormalizer, otherwise it would have been placed in
   * DataDefinitionNormalizer.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $items
   *   An array of data definition properties to be normalized.
   * @param string $format
   *   Format identifier of the current serialization process.
   * @param array $context
   *   Operating context of the serializer.
   *
   * @return array
   *   Array containing one or two nested arrays.
   *   - properties: The array of all normalized properties.
   *   - required: The array of required properties by name.
   */
  protected function normalizeProperties(array $items, $format, array $context = []) {
    $normalized = [];
    foreach ($items as $name => $property) {
      $context['name'] = $name;
      $item = $this->serializer->normalize($property, $format, $context);
      if (!empty($item)) {
        $normalized = NestedArray::mergeDeep($normalized, $item);
      }
    }

    return $normalized;
  }

  /**
   * Determine if the given property is a required element of the schema.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property
   *   The data property to be evaluated.
   *
   * @return bool
   *   Whether the property should be treated as required for schema
   *   purposes.
   */
  protected function requiredProperty(DataDefinitionInterface $property) {
    return $property->isReadOnly() || $property->isRequired();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFormat($format = NULL) {
    if (!isset($format) || !isset($this->format)) {
      return TRUE;
    }

    $parts = explode(':', $format);
    return $parts[0] == $this->format && isset($parts[1]) && $this->describedFormat == $parts[1];
  }

}
