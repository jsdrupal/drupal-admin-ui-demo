<?php

namespace Drupal\jsonapi\Controller;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Acts as request forwarder for \Drupal\jsonapi\Controller\EntityResource.
 *
 * @internal
 */
class RequestHandler {

  /**
   * The JSON API serializer.
   *
   * @var \Drupal\jsonapi\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The JSON API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The field type manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The JSON API link manager.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * Creates a new RequestHandler instance.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The JSON API serializer.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type manager.
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The JSON API link manager.
   */
  public function __construct(SerializerInterface $serializer, ResourceTypeRepositoryInterface $resource_type_repository, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, FieldTypePluginManagerInterface $field_type_manager, LinkManager $link_manager) {
    $this->serializer = $serializer;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->fieldTypeManager = $field_type_manager;
    $this->linkManager = $link_manager;
  }

  /**
   * Handles a JSON API request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The response object.
   */
  public function handle(Request $request, ResourceType $resource_type) {
    $unserialized = $this->deserialize($request, $resource_type);

    // Determine the request parameters that should be passed to the resource
    // plugin.
    $parameters = [];

    $entity_type_id = $resource_type->getEntityTypeId();
    if ($entity = $request->get($entity_type_id)) {
      $parameters[$entity_type_id] = $entity;
    }

    if ($related = $request->get('related')) {
      $parameters['related'] = $related;
    }

    // Invoke the operation on the resource plugin.
    $action = $this->action($request, $resource_type);
    $resource = $this->resourceFactory($resource_type);

    // Only add the unserialized data if there is something there.
    $extra_parameters = $unserialized ? [$unserialized, $request] : [$request];

    return call_user_func_array([$resource, $action], array_merge($parameters, $extra_parameters));
  }

  /**
   * Deserializes request body, if any.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return array|null
   *   An object normalization, if there is a valid request body. NULL if there
   *   is no request body.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown if the request body cannot be decoded, or when no request body was
   *   provided with a POST or PATCH request.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   Thrown if the request body cannot be denormalized.
   */
  protected function deserialize(Request $request, ResourceType $resource_type) {
    if ($request->isMethodSafe(FALSE)) {
      return NULL;
    }

    // Deserialize incoming data if available.
    $received = $request->getContent();
    $unserialized = NULL;
    if (!empty($received)) {
      // First decode the request data. We can then determine if the
      // serialized data was malformed.
      try {
        $unserialized = $this->serializer->decode($received, 'api_json');
      }
      catch (UnexpectedValueException $e) {
        // If an exception was thrown at this stage, there was a problem
        // decoding the data. Throw a 400 http exception.
        throw new BadRequestHttpException($e->getMessage());
      }

      $field_related = $resource_type->getInternalName($request->get('related'));
      try {
        $unserialized = $this->serializer->denormalize($unserialized, $request->get('serialization_class'), 'api_json', [
          'related' => $field_related,
          'target_entity' => $request->get($resource_type->getEntityTypeId()),
          'resource_type' => $resource_type,
        ]);
      }
      // These two serialization exception types mean there was a problem with
      // the structure of the decoded data and it's not valid.
      catch (UnexpectedValueException $e) {
        throw new UnprocessableEntityHttpException($e->getMessage());
      }
      catch (InvalidArgumentException $e) {
        throw new UnprocessableEntityHttpException($e->getMessage());
      }
    }
    elseif ($request->isMethod('POST') || $request->isMethod('PATCH')) {
      throw new BadRequestHttpException('Empty request body.');
    }

    return $unserialized;
  }

  /**
   * Gets the method to execute in the entity resource.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request being handled.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return string
   *   The method to execute in the EntityResource.
   */
  protected function action(Request $request, ResourceType $resource_type) {
    $on_relationship = (bool) $request->get('_on_relationship');
    switch (strtolower($request->getMethod())) {
      case 'head':
      case 'get':
        if ($on_relationship) {
          return 'getRelationship';
        }
        elseif ($request->get('related')) {
          return 'getRelated';
        }
        return $request->get($resource_type->getEntityTypeId()) ? 'getIndividual' : 'getCollection';

      case 'post':
        return ($on_relationship) ? 'createRelationship' : 'createIndividual';

      case 'patch':
        return ($on_relationship) ? 'patchRelationship' : 'patchIndividual';

      case 'delete':
        return ($on_relationship) ? 'deleteRelationship' : 'deleteIndividual';
    }
  }

  /**
   * Get the resource.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return \Drupal\jsonapi\Controller\EntityResource
   *   The instantiated resource.
   */
  protected function resourceFactory(ResourceType $resource_type) {
    $resource = new EntityResource(
      $resource_type,
      $this->entityTypeManager,
      $this->fieldManager,
      $this->fieldTypeManager,
      $this->linkManager,
      $this->resourceTypeRepository
    );
    return $resource;
  }

}
