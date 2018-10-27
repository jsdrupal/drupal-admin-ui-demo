<?php

namespace Drupal\schemata\Schema;

use Drupal\node\Entity\NodeType;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;

/**
 * Specialized schema for Node Entities.
 *
 * Leverages NodeType configuration for additional metadata.
 */
class NodeSchema extends Schema {

  /**
   * NodeType associated with the current bundle.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $nodeType;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityDataDefinitionInterface $entity_type, $bundle = NULL, $properties = []) {
    $this->nodeType = NodeType::load($bundle);
    parent::__construct($entity_type, $bundle, $properties);
  }

  /**
   * {@inheritdoc}
   */
  protected function createDescription($entityType, $bundle = '') {
    $description = $this->nodeType->getDescription();
    if (empty($description)) {
      return parent::createDescription($entityType, $bundle);
    }

    return addslashes(strip_tags($description));;
  }

}
