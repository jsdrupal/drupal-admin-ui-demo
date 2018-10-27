<?php

namespace Drupal\jsonapi\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes 'api_json' format from the 'serializer.formats' container parameter.
 *
 * We want the 'api_json' format to not be supported in the REST module. But the
 * JSON API module also should not have to define al alternative 'serializer'
 * service.
 * This is achieved through removing the 'api_json' format from the
 * 'serializer.formats' container parameter. The consequences of doing that:
 *
 * - the REST module no longer allows this format to be used
 * - the 'serialization.exception.default' service does not support 'api_json',
 *   hence a custom exception subscriber is needed, which this module has:
 *   'jsonapi.exception_subscriber'
 * - the 'serializer' service does support 'api_json'
 *
 * In other words: the 'serializer' service supports 'api_json', but nothing is
 * aware of it. You could only know by calling 'serializer:supportsEncoding()'.
 *
 * @see \Drupal\serialization\RegisterSerializationClassesCompilerPass
 * @see \Drupal\jsonapi\JsonapiServiceProvider::register()
 * @see \Drupal\jsonapi\EventSubscriber\DefaultExceptionSubscriber
 * @see \Drupal\Tests\jsonapi\Functional\RestJsonApiUnsupported
 *
 * @internal
 */
class RemoveJsonapiFormatCompilerPass implements CompilerPassInterface {

  /**
   * Updates the 'serializer.formats' container parameter.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container to process.
   */
  public function process(ContainerBuilder $container) {
    if ($container->hasParameter('serializer.formats')) {
      $filtered_formats = array_filter(
        $container->getParameter('serializer.formats'),
        function ($format) {
          return $format !== 'api_json';
        }
      );
      $container->setParameter('serializer.formats', array_values($filtered_formats));
    }
  }

}
