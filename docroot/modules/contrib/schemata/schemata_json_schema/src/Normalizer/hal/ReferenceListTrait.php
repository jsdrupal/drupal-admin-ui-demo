<?php

namespace Drupal\schemata_json_schema\Normalizer\hal;

/**
 * Passes reference handling to DataReferenceDefinitionHalNormalizer.
 *
 * Both ListDataDefinition and FieldDefinition have the same logic.
 */
trait ReferenceListTrait {

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    /* @var $entity \Drupal\Core\TypedData\ListDataDefinitionInterface */
    // If this list does not wrap a reference, revert to standard JSON behavior.
    if (!$this->isReferenceField($entity)) {
      return parent::normalize($entity, $format, $context);
    }

    // Unlike
    // Drupal\schemata_json_schema\Normalizer\json\ListDataDefinitionNormalizer,
    // this does not return the nested value into the property's 'items'
    // attribute. Instead it returns the normalized reference definition to be
    // merged at the normalized object root. This means the item definition
    // referred to below can choose to add new properties, required values, and
    // so on.
    $context['parent'] = $entity;
    return $this->serializer->normalize(
      $entity->getItemDefinition(),
      $format,
      $context
    );
  }

}
