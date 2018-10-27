<?php

namespace Drupal\schemata_json_schema\Normalizer\json;

use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\Core\TypedData\ListDataDefinitionInterface;

/**
 * Normalizer for ListDataDefinitionInterface objects.
 *
 * Almost all entity properties in the system are a list of values, each value
 * in the "List" might be a ComplexDataDefinitionInterface (an object) or it
 * might be more of a scalar.
 */
class ListDataDefinitionNormalizer extends DataDefinitionNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = '\Drupal\Core\TypedData\ListDataDefinitionInterface';

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    /* @var $entity \Drupal\Core\TypedData\ListDataDefinitionInterface */
    $context['parent'] = $entity;
    $property = $this->extractPropertyData($entity, $context);
    $property['type'] = 'array';

    // This retrieves the definition common to ever item in the list, and
    // serializes it so we can define how members of the array should look.
    // There are no lists that might contain items of different types.
    $property['items'] = $this->serializer->normalize(
      $entity->getItemDefinition(),
      $format,
      $context
    );

    // FieldDefinitionInterface::isRequired() explicitly indicates there must be
    // at least one item in the list. Extending this reasoning, the same must be
    // true of all ListDataDefinitions.
    if ($this->requiredProperty($entity)) {
      $property['minItems'] = 1;
    }

    $normalized = ['properties' => []];
    $normalized['properties'][$context['name']] = $property;
    if ($this->requiredProperty($entity)) {
      $normalized['required'][] = $context['name'];
    }

    return $normalized;
  }

  /**
   * Determine if the current field is a reference field.
   *
   * @param \Drupal\Core\TypedData\ListDataDefinitionInterface $entity
   *   The list definition to be checked.
   *
   * @return bool
   *   TRUE if it is a reference, FALSE otherwise.
   */
  protected function isReferenceField(ListDataDefinitionInterface $entity) {
    $item = $entity->getItemDefinition();
    if ($item instanceof ComplexDataDefinitionInterface) {
      $main = $item->getPropertyDefinition($item->getMainPropertyName());
      // @todo use an interface or API call instead of an object check.
      return ($main instanceof DataReferenceTargetDefinition);
    }

    return FALSE;
  }

}
