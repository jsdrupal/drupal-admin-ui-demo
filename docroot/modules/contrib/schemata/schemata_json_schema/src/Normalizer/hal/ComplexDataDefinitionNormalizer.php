<?php

namespace Drupal\schemata_json_schema\Normalizer\hal;

use Drupal\schemata_json_schema\Normalizer\json\ComplexDataDefinitionNormalizer as JsonComplexDataDefinitionNormalizer;

/**
 * Normalizer for ComplexDataDefinitionInterface for HAL.
 */
class ComplexDataDefinitionNormalizer extends JsonComplexDataDefinitionNormalizer {

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

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    /* @var $entity \Drupal\Core\TypedData\ComplexDataDefinitionInterface */
    // If this does not wrap a reference, revert to standard JSON behavior.
    if (!$this->isReferenceField($entity, $context)) {
      return parent::normalize($entity, $format, $context);
    }

    // Not overriding the $context['parent'] here allows trickle-down of
    // top-level field labels. However, we do need some of the field settings.
    $context['settings'] = $entity->getSettings();
    return $this->serializer->normalize(
      $entity->getPropertyDefinition('entity'),
      $format,
      $context
    );
  }

}
