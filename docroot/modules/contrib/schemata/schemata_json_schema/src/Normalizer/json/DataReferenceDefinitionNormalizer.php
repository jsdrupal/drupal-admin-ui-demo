<?php

namespace Drupal\schemata_json_schema\Normalizer\json;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Normalizer for Entity References.
 *
 * DataReferenceDefinitions are embedded inside ComplexDataDefinitions, and
 * represent a type property. The key for this is usually "entity", and it is
 * found alongside a "target_id" value which refers to the specific entity
 * instance for the reference. The target_id is not normalized by this class,
 * instead it comes through the DataDefinitionNormalizer as a scalar value.
 */
class DataReferenceDefinitionNormalizer extends DataDefinitionNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = '\Drupal\Core\TypedData\DataReferenceDefinitionInterface';

  /**
   * EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs an DataReferenceDefinitionNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    /* @var $entity \Drupal\Core\TypedData\DataReferenceDefinitionInterface */
    if (!$this->validateEntity($entity)) {
      return [];
    }

    // DataDefinitionNormalizer::normalize() results in extraneous structures
    // added to the schema for this field element (e.g., entity)
    return $this->extractPropertyData($entity, $context);
  }

  /**
   * Ensure the entity type is one we support for schema reference.
   *
   * If somehow the entity does not exist, or is not a ContentEntity, skip it.
   *
   * @param mixed $entity
   *   The object to be normalized.
   *
   * @return bool
   *   TRUE if valid for use.
   */
  protected function validateEntity($entity) {
    // Only entity references have a schema.
    // This leads to incompatibility with alternate reference modules such as
    // Dynamic Entity Reference.
    if ($entity->getDataType() != 'entity_reference') {
      return FALSE;
    }

    $entity_type_plugin = $this->entityTypeManager->getDefinition($entity->getConstraint('EntityType'), FALSE);
    if (empty($entity_type_plugin)
      || !($entity_type_plugin->isSubclassOf('\Drupal\Core\Entity\ContentEntityInterface'))) {
      return FALSE;
    }

    return TRUE;
  }

}
