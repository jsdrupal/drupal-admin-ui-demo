<?php

namespace Drupal\jsonapi\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\jsonapi\Controller\EntryPoint;
use Drupal\jsonapi\ParamConverter\ResourceTypeConverter;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Resource\JsonApiDocumentTopLevel;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 *
 * @internal
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The front controller for the JSON API routes.
   *
   * All routes will use this callback to bootstrap the JSON API process.
   *
   * @var string
   */
  const FRONT_CONTROLLER = 'jsonapi.request_handler:handle';

  /**
   * A key with which to flag a route as belonging to the JSON API module.
   *
   * @var string
   */
  const JSON_API_ROUTE_FLAG_KEY = '_is_jsonapi';

  /**
   * The route default key for the route's resource type information.
   *
   * @var string
   */
  const RESOURCE_TYPE_KEY = 'resource_type';

  /**
   * The JSON API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * List of providers.
   *
   * @var string[]
   */
  protected $providerIds;

  /**
   * The JSON API base path.
   *
   * @var string
   */
  protected $jsonApiBasePath;

  /**
   * Instantiates a Routes object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON API resource type repository.
   * @param string[] $authentication_providers
   *   The authentication providers, keyed by ID.
   * @param string $jsonapi_base_path
   *   The JSON API base path.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, array $authentication_providers, $jsonapi_base_path) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->providerIds = array_keys($authentication_providers);
    $this->jsonApiBasePath = $jsonapi_base_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository'),
      $container->getParameter('authentication_providers'),
      $container->getParameter('jsonapi.base_path')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = new RouteCollection();

    // JSON API's routes: entry point + routes for every resource type.
    foreach ($this->resourceTypeRepository->all() as $resource_type) {
      $routes->addCollection(static::getRoutesForResourceType($resource_type, $this->jsonApiBasePath));
    }
    $routes->add('jsonapi.resource_list', static::getEntryPointRoute($this->jsonApiBasePath));

    // Enable all available authentication providers.
    $routes->addOptions(['_auth' => $this->providerIds]);

    // Flag every route as belonging to the JSON API module.
    $routes->addDefaults([static::JSON_API_ROUTE_FLAG_KEY => TRUE]);

    return $routes;
  }

  /**
   * Gets applicable resource routes for a JSON API resource type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for which to get the routes.
   * @param string $path_prefix
   *   The root path prefix.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   A collection of routes for the given resource type.
   */
  protected static function getRoutesForResourceType(ResourceType $resource_type, $path_prefix) {
    // Internal resources have no routes.
    if ($resource_type->isInternal()) {
      return new RouteCollection();
    }

    $routes = new RouteCollection();

    // Collection route like `/jsonapi/node/article`.
    $collection_route = new Route('/' . $resource_type->getPath());
    $collection_route->setMethods($resource_type->isLocatable() ? ['GET', 'POST'] : ['POST']);
    $collection_route->addDefaults(['serialization_class' => JsonApiDocumentTopLevel::class]);
    $collection_route->setRequirement('_csrf_request_header_token', 'TRUE');
    $routes->add(static::getRouteName($resource_type, 'collection'), $collection_route);

    // Individual routes like `/jsonapi/node/article/{uuid}` or
    // `/jsonapi/node/article/{uuid}/relationships/uid`.
    $routes->addCollection(static::getIndividualRoutesForResourceType($resource_type));

    // Add the resource type as a parameter to every resource route.
    foreach ($routes as $route) {
      static::addRouteParameter($route, static::RESOURCE_TYPE_KEY, ['type' => ResourceTypeConverter::PARAM_TYPE_ID]);
      $route->addDefaults([static::RESOURCE_TYPE_KEY => $resource_type->getTypeName()]);
    }

    // Resource routes all have the same controller.
    $routes->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::FRONT_CONTROLLER]);
    $routes->addRequirements(['_jsonapi_custom_query_parameter_names' => 'TRUE']);
    $routes->addPrefix($path_prefix);

    return $routes;
  }

  /**
   * A collection route for the given resource type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type for which the route collection should be created.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The route collection.
   */
  protected static function getIndividualRoutesForResourceType(ResourceType $resource_type) {
    if (!$resource_type->isLocatable()) {
      return new RouteCollection();
    }

    $routes = new RouteCollection();

    $path = $resource_type->getPath();
    $entity_type_id = $resource_type->getEntityTypeId();

    // Individual read, update and remove.
    $individual_route = new Route("/{$path}/{{$entity_type_id}}");
    $individual_route->setMethods(['GET', 'PATCH', 'DELETE']);
    $individual_route->addDefaults(['serialization_class' => JsonApiDocumentTopLevel::class]);
    $individual_route->setRequirement('_csrf_request_header_token', 'TRUE');
    $routes->add(static::getRouteName($resource_type, 'individual'), $individual_route);

    // Get an individual resource's related resources.
    $related_route = new Route("/{$path}/{{$entity_type_id}}/{related}");
    $related_route->setMethods(['GET']);
    $routes->add(static::getRouteName($resource_type, 'related'), $related_route);

    // Read, update, add, or remove an individual resources relationships to
    // other resources.
    $relationship_route = new Route("/{$path}/{{$entity_type_id}}/relationships/{related}");
    $relationship_route->setMethods(['GET', 'POST', 'PATCH', 'DELETE']);
    // @todo: remove the _on_relationship default in https://www.drupal.org/project/jsonapi/issues/2953346.
    $relationship_route->addDefaults(['_on_relationship' => TRUE]);
    $relationship_route->addDefaults(['serialization_class' => EntityReferenceFieldItemList::class]);
    $relationship_route->setRequirement('_csrf_request_header_token', 'TRUE');
    $routes->add(static::getRouteName($resource_type, 'relationship'), $relationship_route);

    // Add entity parameter conversion to every route.
    $routes->addOptions(['parameters' => [$entity_type_id => ['type' => 'entity:' . $entity_type_id]]]);

    return $routes;
  }

  /**
   * Provides the entry point route.
   *
   * @param string $path_prefix
   *   The root path prefix.
   *
   * @return \Symfony\Component\Routing\Route
   *   The entry point route.
   */
  protected function getEntryPointRoute($path_prefix) {
    $entry_point = new Route("/{$path_prefix}");
    $entry_point->addDefaults([RouteObjectInterface::CONTROLLER_NAME => EntryPoint::class . '::index']);
    $entry_point->setRequirement('_permission', 'access jsonapi resource list');
    $entry_point->setMethods(['GET']);
    return $entry_point;
  }

  /**
   * Adds a parameter option to a route, overrides options of the same name.
   *
   * The Symfony Route class only has a method for adding options which
   * overrides any previous values. Therefore, it is tedious to add a single
   * parameter while keeping those that are already set.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to which the parameter is to be added.
   * @param string $name
   *   The name of the parameter.
   * @param mixed $parameter
   *   The parameter's options.
   */
  protected static function addRouteParameter(Route $route, $name, $parameter) {
    $parameters = $route->getOption('parameters') ?: [];
    $parameters[$name] = $parameter;
    $route->setOption('parameters', $parameters);
  }

  /**
   * Get a unique route name for the JSON API resource type and route type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type for which the route collection should be created.
   * @param string $route_type
   *   The route type. E.g. 'individual' or 'collection'.
   *
   * @return string
   *   The generated route name.
   */
  protected static function getRouteName(ResourceType $resource_type, $route_type) {
    return sprintf('jsonapi.%s.%s', $resource_type->getTypeName(), $route_type);
  }

  /**
   * Gets the resource type from a route or request's parameters.
   *
   * @param array $parameters
   *   An array of parameters. These may be obtained from a route's
   *   parameter defaults or from a request object.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType|null
   *   The resource type, NULL if one cannot be found from the given parameters.
   */
  public static function getResourceTypeNameFromParameters(array $parameters) {
    if (isset($parameters[static::JSON_API_ROUTE_FLAG_KEY]) && $parameters[static::JSON_API_ROUTE_FLAG_KEY]) {
      return isset($parameters[static::RESOURCE_TYPE_KEY]) ? $parameters[static::RESOURCE_TYPE_KEY] : NULL;
    }
    return NULL;
  }

}
