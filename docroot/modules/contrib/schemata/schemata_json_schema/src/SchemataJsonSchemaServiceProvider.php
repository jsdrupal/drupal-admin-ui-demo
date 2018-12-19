<?php

namespace Drupal\schemata_json_schema;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provides schemata services that depend directly on HAL.
 */
class SchemataJsonSchemaServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (!isset($modules['hal'])) {
      return;
    }

    // Provide the HAL+JSON version of the Data Reference normalizer here
    // because the hal.link_manager service argument requires HAL.
    $container->register('serializer.normalizer.data_reference_definition.schema_json.hal_json', 'Drupal\schemata_json_schema\Normalizer\hal\DataReferenceDefinitionNormalizer')
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('hal.link_manager'))
      ->addTag('normalizer', ['priority' => 30]);
  }

}
