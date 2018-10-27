<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;
use Drupal\jsonapi\Normalizer\Value\NullFieldNormalizerValue;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Resource\EntityCollection;
use Drupal\jsonapi\LinkManager\LinkManager;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Normalizer class specific for entity reference field objects.
 *
 * @internal
 */
class EntityReferenceFieldNormalizer extends FieldNormalizer implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = EntityReferenceFieldItemListInterface::class;

  /**
   * The link manager.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The field plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $pluginManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Instantiates a EntityReferenceFieldNormalizer object.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The link manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $plugin_manager
   *   The plugin manager for fields.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON API resource type repository.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(LinkManager $link_manager, EntityFieldManagerInterface $field_manager, FieldTypePluginManagerInterface $plugin_manager, ResourceTypeRepositoryInterface $resource_type_repository, EntityRepositoryInterface $entity_repository) {
    $this->linkManager = $link_manager;
    $this->fieldManager = $field_manager;
    $this->pluginManager = $plugin_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    /* @var \Drupal\Core\Field\FieldItemListInterface $field */

    $field_access = $field->access('view', $context['account'], TRUE);
    if (!$field_access->isAllowed()) {
      return new NullFieldNormalizerValue($field_access, 'relationships');
    }

    // Build the relationship object based on the Entity Reference and normalize
    // that object instead.
    $main_property = $field->getItemDefinition()->getMainPropertyName();
    $definition = $field->getFieldDefinition();
    $cardinality = $definition
      ->getFieldStorageDefinition()
      ->getCardinality();
    $entity_list_metadata = [];
    $entity_list = [];
    foreach ($field as $item) {
      // A non-empty entity reference field that refers to a non-existent entity
      // is not a data integrity problem. For example, Term entities' "parent"
      // entity reference field uses target_id zero to refer to the non-existent
      // "<root>" term.
      if (!$item->isEmpty() && $item->get('entity')->getValue() === NULL) {
        $entity_list[] = NULL;
        $entity_list_metadata[] = [
          'links' => [
            'help' => [
              'href' => 'https://www.drupal.org/docs/8/modules/json-api/core-concepts#virtual',
              'meta' => [
                'about' => "Usage and meaning of the 'virtual' resource identifier.",
              ],
            ],
          ],
        ];
        continue;
      }

      // Prepare a list of additional properties stored by the field.
      $metadata = [];
      /** @var \Drupal\Core\TypedData\TypedDataInterface[] $properties */
      // @todo Remove this when JSON API requires Drupal 8.5 or newer.
      $properties = (floatval(\Drupal::VERSION) < 8.5)
        ? $item->getProperties()
        : TypedDataInternalPropertiesHelper::getNonInternalProperties($item);
      foreach ($properties as $property_key => $property) {
        if ($property_key !== $main_property) {
          $metadata[$property_key] = $this->serializer->normalize($property, $format, $context);
        }
      }
      $entity_list_metadata[] = $metadata;

      // Get the referenced entity.
      $entity = $item->get('entity')->getValue();

      if ($this->isInternalResourceType($entity)) {
        continue;
      }

      // And get the translation in the requested language.
      $entity_list[] = $this->entityRepository->getTranslationFromContext($entity);
    }
    $entity_collection = new EntityCollection($entity_list);
    $relationship = new Relationship($this->resourceTypeRepository, $field->getName(), $entity_collection, $field->getEntity(), $field_access, $cardinality, $main_property, $entity_list_metadata);
    return $this->serializer->normalize($relationship, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    // If we get to here is through a write method on a relationship operation.
    /** @var \Drupal\jsonapi\ResourceType\ResourceType $resource_type */
    $resource_type = $context['resource_type'];
    $entity_type_id = $resource_type->getEntityTypeId();
    $field_definitions = $this->fieldManager->getFieldDefinitions(
      $entity_type_id,
      $resource_type->getBundle()
    );
    if (empty($context['related']) || empty($field_definitions[$context['related']])) {
      throw new BadRequestHttpException('Invalid or missing related field.');
    }
    /* @var \Drupal\field\Entity\FieldConfig $field_definition */
    $field_definition = $field_definitions[$context['related']];
    // This is typically 'target_id'.
    $item_definition = $field_definition->getItemDefinition();
    $property_key = $item_definition->getMainPropertyName();
    $target_resource_types = $resource_type->getRelatableResourceTypesByField($context['related']);
    $target_resource_type_names = array_map(function (ResourceType $resource_type) {
      return $resource_type->getTypeName();
    }, $target_resource_types);

    $is_multiple = $field_definition->getFieldStorageDefinition()->isMultiple();
    $data = $this->massageRelationshipInput($data, $is_multiple);
    $values = array_map(function ($value) use ($property_key, $target_resource_type_names) {
      // Make sure that the provided type is compatible with the targeted
      // resource.
      if (!in_array($value['type'], $target_resource_type_names)) {
        throw new BadRequestHttpException(sprintf(
          'The provided type (%s) does not mach the destination resource types (%s).',
          $value['type'],
          implode(', ', $target_resource_type_names)
        ));
      }

      // Load the entity by UUID.
      list($entity_type_id,) = explode('--', $value['type']);
      $entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $value['id']);
      $value['id'] = $entity ? $entity->id() : NULL;

      $properties = [$property_key => $value['id']];
      // Also take into account additional properties provided by the field
      // type.
      if (!empty($value['meta'])) {
        foreach ($value['meta'] as $meta_key => $meta_value) {
          $properties[$meta_key] = $meta_value;
        }
      }
      return $properties;
    }, $data['data']);
    return $this->pluginManager
      ->createFieldItemList($context['target_entity'], $context['related'], $values);
  }

  /**
   * Validates and massages the relationship input depending on the cardinality.
   *
   * @param array $data
   *   The input data from the body.
   * @param bool $is_multiple
   *   Indicates if the relationship is to-many.
   *
   * @return array
   *   The massaged data array.
   */
  protected function massageRelationshipInput(array $data, $is_multiple) {
    if ($is_multiple) {
      if (!is_array($data['data'])) {
        throw new BadRequestHttpException('Invalid body payload for the relationship.');
      }
      // Leave the invalid elements.
      $invalid_elements = array_filter($data['data'], function ($element) {
        return empty($element['type']) || empty($element['id']);
      });
      if ($invalid_elements) {
        throw new BadRequestHttpException('Invalid body payload for the relationship.');
      }
    }
    else {
      // For to-one relationships you can have a NULL value.
      if (is_null($data['data'])) {
        return ['data' => []];
      }
      if (empty($data['data']['type']) || empty($data['data']['id'])) {
        throw new BadRequestHttpException('Invalid body payload for the relationship.');
      }
      $data['data'] = [$data['data']];
    }
    return $data;
  }

  /**
   * Determines if the given entity is of an internal resource type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to check the internal status.
   *
   * @return bool
   *   TRUE if the entity's resource type is internal, FALSE otherwise.
   */
  protected function isInternalResourceType(EntityInterface $entity) {
    return ($resource_type = $this->resourceTypeRepository->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    )) && $resource_type->isInternal();
  }

}
