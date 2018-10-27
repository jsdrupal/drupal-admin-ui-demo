<?php

namespace Drupal\schemata\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\schemata\SchemaFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Contains callback methods for dynamic routes.
 */
class Controller extends ControllerBase {

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The schema factory.
   *
   * @var \Drupal\schemata\SchemaFactory
   */
  protected $schemaFactory;

  /**
   * The cacheable response.
   *
   * @var \Drupal\Core\Cache\CacheableResponseInterface
   */
  protected $response;

  /**
   * Controller constructor.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param \Drupal\schemata\SchemaFactory $schema_factory
   *   The schema factory.
   * @param \Drupal\Core\Cache\CacheableResponseInterface $response
   *   The cacheable response.
   */
  public function __construct(SerializerInterface $serializer, SchemaFactory $schema_factory, CacheableResponseInterface $response) {
    $this->serializer = $serializer;
    $this->schemaFactory = $schema_factory;
    $this->response = $response;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('serializer'),
      $container->get('schemata.schema_factory'),
      new CacheableResponse()
    );
  }

  /**
   * Serializes a entity type or bundle definition.
   *
   * We have 2 different data formats involved. One is the schema format (for
   * instance JSON Schema) and the other one is the format that the schema is
   * describing (for instance jsonapi, json, hal+json, …). We need to provide
   * both formats. Something like: ?_format=schema_json&_describes=api_json.
   *
   * @param string $entity_type_id
   *   The entity type ID to describe.
   * @param string $bundle
   *   The (optional) bundle to describe.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Cache\CacheableResponse
   *   The response object.
   */
  public function serialize($entity_type_id, Request $request, $bundle = NULL) {
    $parts = $this->extractFormatNames($request);

    // Load the data to serialize from the route information on the current
    // request.
    $schema = $this->schemaFactory->create($entity_type_id, $bundle);
    // Serialize the entity type/bundle definition.
    $format = implode(':', $parts);
    $content = $this->serializer->serialize($schema, $format);

    // Finally, set the contents of the response and return it.
    $this->response->addCacheableDependency($schema);
    $cacheable_dependency = (new CacheableMetadata())
      ->addCacheContexts(['url.query_args:_describes']);
    $this->response->addCacheableDependency($cacheable_dependency);
    $this->response->setContent($content);
    $this->response->headers->set('Content-Type', $request->getMimeType($parts[0]));
    return $this->response;
  }

  /**
   * Helper function that inspects the request to extract the formats.
   *
   * Extracts the format of the response and media type being described.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   An array containing the format of the output and the media type being
   *   described.
   */
  protected function extractFormatNames(Request $request) {
    return [
      $request->getRequestFormat(),
      $request->query->get('_describes', ''),
    ];
  }

}
