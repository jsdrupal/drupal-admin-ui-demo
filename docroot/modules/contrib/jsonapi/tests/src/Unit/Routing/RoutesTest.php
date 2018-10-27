<?php

namespace Drupal\Tests\jsonapi\Unit\Routing;

use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Drupal\jsonapi\Routing\Routes;
use Drupal\Tests\UnitTestCase;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\jsonapi\Routing\Routes
 * @group jsonapi
 *
 * @internal
 */
class RoutesTest extends UnitTestCase {

  /**
   * List of routes objects for the different scenarios.
   *
   * @var \Drupal\jsonapi\Routing\Routes[]
   */
  protected $routes;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $type_1 = new ResourceType('entity_type_1', 'bundle_1_1', EntityInterface::class);
    $type_2 = new ResourceType('entity_type_2', 'bundle_2_1', EntityInterface::class, TRUE);
    $relatable_resource_types = [
      'external' => [$type_1],
      'internal' => [$type_2],
      'both' => [$type_1, $type_2],
    ];
    $type_1->setRelatableResourceTypes($relatable_resource_types);
    $type_2->setRelatableResourceTypes($relatable_resource_types);
    // This type ensures that we can create routes for bundle IDs which might be
    // cast from strings to integers.  It should not affect related resource
    // routing.
    $type_3 = new ResourceType('entity_type_3', '123', EntityInterface::class, TRUE);
    $type_3->setRelatableResourceTypes([]);
    $resource_type_repository = $this->prophesize(ResourceTypeRepository::class);
    $resource_type_repository->all()->willReturn([$type_1, $type_2, $type_3]);
    $container = $this->prophesize(ContainerInterface::class);
    $container->get('jsonapi.resource_type.repository')->willReturn($resource_type_repository->reveal());
    $container->getParameter('jsonapi.base_path')->willReturn('/jsonapi');
    $container->getParameter('authentication_providers')->willReturn([
      'lorem' => [],
      'ipsum' => [],
    ]);

    $this->routes['ok'] = Routes::create($container->reveal());
  }

  /**
   * @covers ::routes
   */
  public function testRoutesCollection() {
    // Get the route collection and start making assertions.
    $routes = $this->routes['ok']->routes();

    // Make sure that there are 4 routes for the non-internal resource and 1 for
    // the entry point.
    $this->assertEquals(5, $routes->count());

    $iterator = $routes->getIterator();
    // Check the collection route.
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $iterator->offsetGet('jsonapi.entity_type_1--bundle_1_1.collection');
    $this->assertSame('/jsonapi/entity_type_1/bundle_1_1', $route->getPath());
    $this->assertSame(['lorem', 'ipsum'], $route->getOption('_auth'));
    $this->assertSame('entity_type_1--bundle_1_1', $route->getDefault(Routes::RESOURCE_TYPE_KEY));
    $this->assertEquals(['GET', 'POST'], $route->getMethods());
    $this->assertSame(Routes::FRONT_CONTROLLER, $route->getDefault(RouteObjectInterface::CONTROLLER_NAME));
    $this->assertSame('Drupal\jsonapi\Resource\JsonApiDocumentTopLevel', $route->getDefault('serialization_class'));
  }

  /**
   * @covers ::routes
   */
  public function testRoutesIndividual() {
    // Get the route collection and start making assertions.
    $iterator = $this->routes['ok']->routes()->getIterator();

    // Check the individual route.
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $iterator->offsetGet('jsonapi.entity_type_1--bundle_1_1.individual');
    $this->assertSame('/jsonapi/entity_type_1/bundle_1_1/{entity_type_1}', $route->getPath());
    $this->assertSame('entity_type_1--bundle_1_1', $route->getDefault(Routes::RESOURCE_TYPE_KEY));
    $this->assertEquals(['GET', 'PATCH', 'DELETE'], $route->getMethods());
    $this->assertSame(Routes::FRONT_CONTROLLER, $route->getDefault(RouteObjectInterface::CONTROLLER_NAME));
    $this->assertSame('Drupal\jsonapi\Resource\JsonApiDocumentTopLevel', $route->getDefault('serialization_class'));
    $this->assertSame(['lorem', 'ipsum'], $route->getOption('_auth'));
    $this->assertEquals([
      'entity_type_1' => ['type' => 'entity:entity_type_1'],
      'resource_type' => ['type' => 'jsonapi_resource_type'],
    ], $route->getOption('parameters'));
  }

  /**
   * @covers ::routes
   */
  public function testRoutesRelated() {
    // Get the route collection and start making assertions.
    $iterator = $this->routes['ok']->routes()->getIterator();

    // Check the related route.
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $iterator->offsetGet('jsonapi.entity_type_1--bundle_1_1.related');
    $this->assertSame('/jsonapi/entity_type_1/bundle_1_1/{entity_type_1}/{related}', $route->getPath());
    $this->assertSame('entity_type_1--bundle_1_1', $route->getDefault(Routes::RESOURCE_TYPE_KEY));
    $this->assertEquals(['GET'], $route->getMethods());
    $this->assertSame(Routes::FRONT_CONTROLLER, $route->getDefault(RouteObjectInterface::CONTROLLER_NAME));
    $this->assertSame(['lorem', 'ipsum'], $route->getOption('_auth'));
    $this->assertEquals([
      'entity_type_1' => ['type' => 'entity:entity_type_1'],
      'resource_type' => ['type' => 'jsonapi_resource_type'],
    ], $route->getOption('parameters'));
  }

  /**
   * @covers ::routes
   */
  public function testRoutesRelationships() {
    // Get the route collection and start making assertions.
    $iterator = $this->routes['ok']->routes()->getIterator();

    // Check the relationships route.
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $iterator->offsetGet('jsonapi.entity_type_1--bundle_1_1.relationship');
    $this->assertSame('/jsonapi/entity_type_1/bundle_1_1/{entity_type_1}/relationships/{related}', $route->getPath());
    $this->assertSame('entity_type_1--bundle_1_1', $route->getDefault(Routes::RESOURCE_TYPE_KEY));
    $this->assertEquals(['GET', 'POST', 'PATCH', 'DELETE'], $route->getMethods());
    $this->assertSame(Routes::FRONT_CONTROLLER, $route->getDefault(RouteObjectInterface::CONTROLLER_NAME));
    $this->assertSame(['lorem', 'ipsum'], $route->getOption('_auth'));
    $this->assertEquals([
      'entity_type_1' => ['type' => 'entity:entity_type_1'],
      'resource_type' => ['type' => 'jsonapi_resource_type'],
    ], $route->getOption('parameters'));
    $this->assertSame('Drupal\Core\Field\EntityReferenceFieldItemList', $route->getDefault('serialization_class'));
  }

  /**
   * Ensures that the expected routes are created or not created.
   *
   * @dataProvider expectedRoutes
   */
  public function testRoutes($route) {
    $this->assertArrayHasKey($route, $this->routes['ok']->routes()->all());
  }

  /**
   * Lists routes which should have been created.
   */
  public function expectedRoutes() {
    return [
      ['jsonapi.entity_type_1--bundle_1_1.individual'],
      ['jsonapi.entity_type_1--bundle_1_1.collection'],
      ['jsonapi.entity_type_1--bundle_1_1.related'],
      ['jsonapi.entity_type_1--bundle_1_1.relationship'],
      ['jsonapi.resource_list'],
    ];
  }

  /**
   * Ensures that no routes are created for internal resources.
   *
   * @dataProvider notExpectedRoutes
   */
  public function testInternalRoutes($route) {
    $this->assertArrayNotHasKey($route, $this->routes['ok']->routes()->all());
  }

  /**
   * Lists routes which should have been created.
   */
  public function notExpectedRoutes() {
    return [
      ['jsonapi.entity_type_2--bundle_2_1.individual'],
      ['jsonapi.entity_type_2--bundle_2_1.collection'],
      ['jsonapi.entity_type_2--bundle_2_1.related'],
      ['jsonapi.entity_type_2--bundle_2_1.relationship'],
    ];
  }

}
