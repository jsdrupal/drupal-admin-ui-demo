<?php

namespace Drupal\jsonapi\DependencyInjection\Compiler;

use Drupal\serialization\RegisterSerializationClassesCompilerPass as DrupalRegisterSerializationClassesCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds services tagged JSON API-only normalizers to the Serializer.
 *
 * Services tagged with 'jsonapi_normalizer_do_not_use_removal_imminent' will be
 * added to the JSON API serializer. As should be clear by the service tag,
 * *no* extensions should provide these services. They will not work in the
 * future. The proper way to affect JSON API output is to implement DataType
 * level normalizers and/or implement computed entity fields.
 *
 * @see jsonapi.api.php
 *
 * @internal
 */
class RegisterSerializationClassesCompilerPass extends DrupalRegisterSerializationClassesCompilerPass {

  /**
   * The service ID.
   *
   * @const string
   */
  const OVERRIDDEN_SERVICE_ID = 'jsonapi.serializer_do_not_use_removal_imminent';

  /**
   * The service tag that only JSON API normalizers should use.
   *
   * @const string
   */
  const OVERRIDDEN_SERVICE_TAG = 'jsonapi_normalizer_do_not_use_removal_imminent';

  /**
   * The ID for the JSON API format.
   *
   * @const string
   */
  const FORMAT = 'api_json';

  /**
   * Adds services to the JSON API Serializer.
   *
   * This code is copied from the class parent with two modifications. The
   * service id has been changed and the service tag has been updated.
   *
   * ID: 'serializer' -> 'jsonapi.serializer_do_not_use_removal_imminent'
   * Tag: 'normalizer' -> 'jsonapi_normalizer_do_not_use_removal_imminent'
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container to process.
   */
  public function process(ContainerBuilder $container) {
    $definition = $container->getDefinition(static::OVERRIDDEN_SERVICE_ID);

    // Retrieve registered Normalizers and Encoders from the container.
    foreach ($container->findTaggedServiceIds(static::OVERRIDDEN_SERVICE_TAG) as $id => $attributes) {
      // Normalizers are not an API: mark private.
      $container->getDefinition($id)->setPublic(FALSE);

      // If there is a BC key present, pass this to determine if the normalizer
      // should be skipped.
      if (isset($attributes[0]['bc']) && $this->normalizerBcSettingIsEnabled($attributes[0]['bc'], $attributes[0]['bc_config_name'])) {
        continue;
      }

      $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
      $normalizers[$priority][] = new Reference($id);
    }
    foreach ($container->findTaggedServiceIds('encoder') as $id => $attributes) {
      // Encoders are not an API: mark private.
      $container->getDefinition($id)->setPublic(FALSE);

      $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
      $encoders[$priority][] = new Reference($id);
    }

    // Add the registered Normalizers and Encoders to the Serializer.
    if (!empty($normalizers)) {
      $definition->replaceArgument(0, $this->sort($normalizers));
    }
    if (!empty($encoders)) {
      $definition->replaceArgument(1, $this->sort($encoders));
    }

    // Set the JSON API format and format_provider.
    $container->setParameter(
      static::OVERRIDDEN_SERVICE_ID . '.formats',
      [static::FORMAT]
    );
    $container->setParameter(
      static::OVERRIDDEN_SERVICE_ID . '.format_providers',
      [static::FORMAT => 'jsonapi']
    );
  }

}
