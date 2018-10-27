<?php

namespace Drupal\jsonapi\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\Routing\Routes;
use Drupal\schemata\SchemaFactory;
use JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Response subscriber that validates a JSON API response.
 *
 * This must run after ResourceResponseSubscriber.
 *
 * @see \Drupal\rest\EventSubscriber\ResourceResponseSubscriber
 * @internal
 */
class ResourceResponseValidator implements EventSubscriberInterface {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The JSON API logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The schema validator.
   *
   * This property will only be set if the validator library is available.
   *
   * @var \JsonSchema\Validator|null
   */
  protected $validator;

  /**
   * The schemata schema factory.
   *
   * This property will only be set if the schemata module is installed.
   *
   * @var \Drupal\schemata\SchemaFactory|null
   */
  protected $schemaFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The application's root file path.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * Constructs a ResourceResponseValidator object.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   * @param \Psr\Log\LoggerInterface $logger
   *   The JSON API logger channel.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param string $app_root
   *   The application's root file path.
   */
  public function __construct(SerializerInterface $serializer, LoggerInterface $logger, ModuleHandlerInterface $module_handler, $app_root) {
    $this->serializer = $serializer;
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->appRoot = $app_root;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse'];
    return $events;
  }

  /**
   * Sets the validator service if available.
   */
  public function setValidator(Validator $validator = NULL) {
    if ($validator) {
      $this->validator = $validator;
    }
    elseif (class_exists(Validator::class)) {
      $this->validator = new Validator();
    }
  }

  /**
   * Injects the schema factory.
   *
   * @param \Drupal\schemata\SchemaFactory $schema_factory
   *   The schema factory service.
   */
  public function setSchemaFactory(SchemaFactory $schema_factory) {
    $this->schemaFactory = $schema_factory;
  }

  /**
   * Validates JSON API responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function onResponse(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof ResourceResponse) {
      return;
    }

    $this->doValidateResponse($response, $event->getRequest());
  }

  /**
   * Wraps validation in an assert to prevent execution in production.
   *
   * @see self::validateResponse
   */
  public function doValidateResponse(Response $response, Request $request) {
    if (PHP_MAJOR_VERSION >= 7 || assert_options(ASSERT_ACTIVE)) {
      assert($this->validateResponse($response, $request), 'A JSON API response failed validation (see the logs for details). Please report this in the issue queue on drupal.org');
    }
  }

  /**
   * Validates a response against the JSON API specification.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response to validate.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request containing info about what to validate.
   *
   * @return bool
   *   FALSE if the response failed validation, otherwise TRUE.
   */
  protected function validateResponse(Response $response, Request $request) {
    // If the validator isn't set, then the validation library is not installed.
    if (!$this->validator) {
      return TRUE;
    }

    // Do not use Json::decode here since it coerces the response into an
    // associative array, which creates validation errors.
    $response_data = json_decode($response->getContent());
    if (empty($response_data)) {
      return TRUE;
    }

    $schema_ref = sprintf(
      'file://%s/schema.json',
      implode('/', [
        $this->appRoot,
        $this->moduleHandler->getModule('jsonapi')->getPath(),
      ])
    );
    $generic_jsonapi_schema = (object) ['$ref' => $schema_ref];
    $is_valid = $this->validateSchema($generic_jsonapi_schema, $response_data);
    if (!$is_valid) {
      return FALSE;
    }

    // This will be set if the schemata module is present.
    if (!$this->schemaFactory) {
      // Fall back the valid generic result since schemata is absent.
      return TRUE;
    }

    // Get the schema for the current resource. For that we will need to
    // introspect the request to find the entity type and bundle matched by the
    // router.
    $resource_type = $request->get(Routes::RESOURCE_TYPE_KEY);
    $route_name = $request->attributes->get(RouteObjectInterface::ROUTE_NAME);

    // We shouldn't validate related/relationships.
    $is_related = strpos($route_name, '.related') !== FALSE;
    $is_relationship = strpos($route_name, '.relationship') !== FALSE;
    if ($is_related || $is_relationship) {
      // Fall back the valid generic result since schemata is absent.
      return TRUE;
    }

    $entity_type_id = $resource_type->getEntityTypeId();
    $bundle = $resource_type->getBundle();
    $output_format = 'schema_json';
    $described_format = 'api_json';

    $schema_object = $this->schemaFactory->create($entity_type_id, $bundle);
    $format = $output_format . ':' . $described_format;
    $output = $this->serializer->serialize($schema_object, $format);
    $specific_schema = Json::decode($output);
    if (!$specific_schema) {
      return $is_valid;
    }

    // We need to individually validate each collection resource object.
    $is_collection = strpos($route_name, '.collection') !== FALSE;

    // Iterate over each resource object and check the schema.
    return array_reduce(
      $is_collection ? $response_data->data : [$response_data->data],
      function ($valid, $resource_object) use ($specific_schema) {
        // Validating the schema first ensures that every object is processed.
        return $this->validateSchema($specific_schema, $resource_object) && $valid;
      },
      TRUE
    );
  }

  /**
   * Validates a string against a JSON Schema. It logs any possible errors.
   *
   * @param object $schema
   *   The JSON Schema object.
   * @param string $response_data
   *   The JSON string to validate.
   *
   * @return bool
   *   TRUE if the string is a valid instance of the schema. FALSE otherwise.
   */
  protected function validateSchema($schema, $response_data) {
    $this->validator->check($response_data, $schema);
    $is_valid = $this->validator->isValid();
    if (!$is_valid) {
      $this->logger->debug("Response failed validation.\nResponse:\n@data\n\nErrors:\n@errors", [
        '@data' => Json::encode($response_data),
        '@errors' => Json::encode($this->validator->getErrors()),
      ]);
    }
    return $is_valid;
  }

}
