<?php

namespace Drupal\schemata\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class Routes.
 *
 * Dynamic routes for the data models.
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The front controller for the JSON API routes.
   *
   * All routes will use this callback to bootstrap the JSON API process.
   *
   * @var string
   */
  const CONTROLLER = '\Drupal\schemata\Controller\Controller::serialize';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Routes constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   Entity bundle info.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * The route generator.
   */
  public function routes() {
    $route_collection = new RouteCollection();
    // Loop through all the entity types.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      $entity_type_id = $entity_type->id();
      // Add a route for all entity types.
      $route_collection->add($this->createRouteName($entity_type_id), $this->createRoute($entity_type_id));

      // If this entity type has a bundle entity type,
      // then add a route for each bundle.
      if ($entity_type->getBundleEntityType()) {
        // Loop through all the bundles for the entity type.
        $bundles_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
        foreach (array_keys($bundles_info) as $bundle) {
          $route_collection->add($this->createRouteName($entity_type_id, $bundle), $this->createRoute($entity_type_id, $bundle));
        }
      }
    }
    return $route_collection;
  }

  /**
   * Creates a route for a entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle name.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function createRoute($entity_type_id, $bundle = NULL) {
    $path = $this->getRoutePath($entity_type_id, $bundle);
    $route = new Route($path);
    $route->setRequirement('_permission', 'access schemata data models');
    $route->setMethods(['GET']);
    $defaults = [
      'entity_type_id' => $entity_type_id,
      RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER,
    ];
    if ($bundle) {
      $defaults['bundle'] = $bundle;
    }
    $route->setDefaults($defaults);
    return $route;
  }

  /**
   * Creates a route name for a entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle name.
   *
   * @return string
   *   The route name.
   */
  protected function createRouteName($entity_type_id, $bundle = NULL) {
    return $bundle ? sprintf('schemata.%s:%s', $entity_type_id, $bundle) : sprintf('schemata.%s', $entity_type_id);
  }

  /**
   * Creates a route path for a entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle name.
   *
   * @return string
   *   The route path.
   */
  protected function getRoutePath($entity_type_id, $bundle = NULL) {
    $path = $bundle ?
      sprintf('/schemata/%s/%s', $entity_type_id, $bundle) :
      sprintf('/schemata/%s', $entity_type_id);
    return $path;
  }

}
