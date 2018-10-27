<?php

namespace Drupal\schemata_json_schema\Plugin\schemata_json_schema\type_mapper;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for TypeMappers plugins.
 *
 * An empty, subclassed plugin will use the default behavior here, including the
 * use of the plugin ID as the JSON Schema type.
 *
 * This is slightly different from the behavior of the fallback plugin, which
 * uses the data type off the DataDefinition.
 *
 * @see \Drupal\schemata_json_schema\Plugin\Type\TypeMapperPluginManager
 * @see \Drupal\schemata_json_schema\Plugin\schemata_json_schema\type_mapper\FallbackTypeMapper
 */
class TypeMapperBase extends PluginBase implements TypeMapperInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMappedValue(DataDefinitionInterface $property) {
    $value = [
      'type' => $this->getPluginId(),
    ];

    if ($item = $property->getLabel()) {
      $value['title'] = $item;
    }
    if ($item = $property->getDescription()) {
      $value['description'] = addslashes(strip_tags($item));
    }

    return $value;
  }

}
