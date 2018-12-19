<?php

namespace Drupal\schemata\Schema;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Schema class that describes a Drupal Entity or Entity Type.
 *
 * This can be used as a base class, replaced by another class implementing
 * SchemaInterface, or used on it's own as-is.
 */
class Schema implements SchemaInterface, RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * The Entity Type bundle this schema describes, if one exists.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The Entity Type this schema describes.
   *
   * Key methods for serialization includes getLabel() and id().
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   *
   * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21TypedData%21EntityDataDefinitionInterface.php/interface/EntityDataDefinitionInterface/8.1.x
   */
  protected $entityType;

  /**
   * Metadata values that describe the schema.
   *
   * @var array
   */
  protected $metadata = [];

  /**
   * Typed Data objects for all properties on the entity type/bundle.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface[]
   *
   * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21TypedData%21DataDefinitionInterface.php/interface/DataDefinitionInterface/8.1.x
   * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21TypedData%21ComplexDataDefinitionInterface.php/interface/ComplexDataDefinitionInterface/8.1.x
   * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21TypedData%21DataReferenceDefinitionInterface.php/interface/DataReferenceDefinitionInterface/8.1.x
   * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21FieldDefinitionInterface.php/interface/FieldDefinitionInterface/8.1.x
   */
  protected $properties = [];

  /**
   * Creates a Schema object.
   *
   * @param \Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface $entity_type
   *   The Entity Type definition.
   * @param string $bundle
   *   The Bundle data definition.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   Typed data properties for the schema's initial creation.
   */
  public function __construct(EntityDataDefinitionInterface $entity_type, $bundle = NULL, array $properties = []) {
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
    $this->addProperties($properties);

    // These cache tags seem like they are essential to Schema cache variation.
    // It is not clear how to verify these cache tags, or how to pull them from
    // the injected dependencies.
    $this->addCacheableDependency((new CacheableMetadata())->addCacheTags(
      [
        'entity_bundles',
        'entity_field_info',
        'entity_types',
      ]
    ));

    $this->metadata['title'] = $this->createTitle(
      $this->getEntityTypeId(),
      $this->getBundleId()
    ) . ' Schema';

    $this->metadata['description'] = $this->createDescription(
      $this->getEntityTypeId(),
      $this->getBundleId()
    );

  }

  /**
   * {@inheritdoc}
   */
  public function addProperties(array $properties) {
    $this->properties += $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->entityType->getEntityTypeId();
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleId() {
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties() {
    return $this->properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata() {
    return $this->metadata;
  }

  /**
   * Generates a title for the schema based on a simple trio of parameters.
   *
   * @param string $required
   *   This value will be used as the prefix.
   * @param string $optional
   *   Optional value to be separated from the required prefix if present.
   * @param string $sep
   *   Separator string to be used.
   *
   * @return string
   *   The title value.
   */
  protected function createTitle($required, $optional = '', $sep = ':') {
    return empty($optional) ? $required : $required . $sep . $optional;
  }

  /**
   * Generates a description for the schema based on the types.
   *
   * @param string $entityType
   *   Required entity type which the schema defines.
   * @param string $bundle
   *   Optional value to be separated from the required prefix if present.
   *
   * @return string
   *   The description value.
   */
  protected function createDescription($entityType, $bundle = '') {
    $output = "Describes the payload for '$entityType' entities";
    if (!empty($bundle)) {
      $output .= " of the '$bundle' bundle.";
    }
    else {
      $output .= '.';
    }

    return $output;
  }

  /**
   * Magic method: Unsets a property.
   *
   * @param string $name
   *   The name of the property to unset; e.g., 'title' or 'name'.
   */
  public function __unset($name) {
    unset($this->properties[$name]);
  }

  /**
   * Magic method: Determines whether a property is set.
   *
   * @param string $name
   *   The name of the property to check; e.g., 'title' or 'name'.
   *
   * @return bool
   *   Returns TRUE if the property exists and is set, FALSE otherwise.
   */
  public function __isset($name) {
    return isset($this->properties[$name]);
  }

  /**
   * Magic method: Sets a property value.
   *
   * @param string $name
   *   The name of the property to set; e.g., 'title' or 'name'.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $value
   *   The value to set.
   */
  public function __set($name, DataDefinitionInterface $value) {
    $this->properties[$name] = $value;
  }

  /**
   * Magic method: Gets a property value.
   *
   * @param string $name
   *   The name of the property to get; e.g., 'title' or 'name'.
   */
  public function __get($name) {
    $this->properties[$name] = $value;
  }

}
