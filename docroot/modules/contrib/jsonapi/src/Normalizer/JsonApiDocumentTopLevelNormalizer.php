<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\Context\FieldResolver;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\Normalizer\Value\JsonApiDocumentTopLevelNormalizerValue;
use Drupal\jsonapi\Resource\EntityCollection;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\Resource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Normalizes the top-level document according to the JSON API specification.
 *
 * @see \Drupal\jsonapi\Resource\JsonApiDocumentTopLevel
 *
 * @internal
 */
class JsonApiDocumentTopLevelNormalizer extends NormalizerBase implements DenormalizerInterface, NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = JsonApiDocumentTopLevel::class;

  /**
   * The link manager to get the links.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The JSON API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * Constructs a JsonApiDocumentTopLevelNormalizer object.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The link manager to get the links.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON API resource type repository.
   */
  public function __construct(LinkManager $link_manager, EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->linkManager = $link_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    // Validate a few common errors in document formatting.
    $this->validateRequestBody($data);

    $normalized = [];

    if (!empty($data['data']['attributes'])) {
      $normalized = $data['data']['attributes'];
    }

    if (!empty($data['data']['id'])) {
      $resource_type = $this->resourceTypeRepository->getByTypeName($data['data']['type']);
      $uuid_key = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId())->getKey('uuid');
      $normalized[$uuid_key] = $data['data']['id'];
    }

    if (!empty($data['data']['relationships'])) {
      // Turn all single object relationship data fields into an array of
      // objects.
      $relationships = array_map(function ($relationship) {
        if (isset($relationship['data']['type']) && isset($relationship['data']['id'])) {
          return ['data' => [$relationship['data']]];
        }
        else {
          return $relationship;
        }
      }, $data['data']['relationships']);

      // Get an array of ids for every relationship.
      $relationships = array_map(function ($relationship) {
        if (empty($relationship['data'])) {
          return [];
        }
        if (empty($relationship['data'][0]['id'])) {
          throw new BadRequestHttpException("No ID specified for related resource");
        }
        $id_list = array_column($relationship['data'], 'id');
        if (empty($relationship['data'][0]['type'])) {
          throw new BadRequestHttpException("No type specified for related resource");
        }
        if (!$resource_type = $this->resourceTypeRepository->getByTypeName($relationship['data'][0]['type'])) {
          throw new BadRequestHttpException("Invalid type specified for related resource: '" . $relationship['data'][0]['type'] . "'");
        }

        $entity_type_id = $resource_type->getEntityTypeId();
        try {
          $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
        }
        catch (PluginNotFoundException $e) {
          throw new BadRequestHttpException("Invalid type specified for related resource: '" . $relationship['data'][0]['type'] . "'");
        }
        // In order to maintain the order ($delta) of the relationships, we need
        // to load the entities and create a mapping between id and uuid.
        $related_entities = array_values($entity_storage->loadByProperties(['uuid' => $id_list]));
        $map = [];
        foreach ($related_entities as $related_entity) {
          $map[$related_entity->uuid()] = $related_entity->id();
        }

        // $id_list has the correct order of uuids. We stitch this together with
        // $map which contains loaded entities, and then bring in the correct
        // meta values from the relationship, whose deltas match with $id_list.
        $canonical_ids = [];
        foreach ($id_list as $delta => $uuid) {
          if (empty($map[$uuid])) {
            continue;
          }
          $reference_item = [
            'target_id' => $map[$uuid],
          ];
          if (isset($relationship['data'][$delta]['meta'])) {
            $reference_item += $relationship['data'][$delta]['meta'];
          }
          $canonical_ids[] = $reference_item;
        }

        return array_filter($canonical_ids);
      }, $relationships);

      // Add the relationship ids.
      $normalized = array_merge($normalized, $relationships);
    }
    // Override deserialization target class with the one in the ResourceType.
    $class = $context['resource_type']->getDeserializationTargetClass();

    return $this
      ->serializer
      ->denormalize($normalized, $class, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $data = $object->getData();
    if (empty($context['expanded'])) {
      $context += $this->expandContext($context['request'], $context['resource_type']);
    }

    if ($data instanceof EntityReferenceFieldItemListInterface) {
      $normalizer_values = [
        $this->serializer->normalize($data, $format, $context),
      ];
      $link_context = ['link_manager' => $this->linkManager];
      return new JsonApiDocumentTopLevelNormalizerValue($normalizer_values, $context, $link_context, FALSE);
    }
    $is_collection = $data instanceof EntityCollection;
    $include_count = $context['resource_type']->includeCount();
    // To improve the logical workflow deal with an array at all times.
    $entities = $is_collection ? $data->toArray() : [$data];
    $context['has_next_page'] = $is_collection ? $data->hasNextPage() : FALSE;

    if ($include_count) {
      $context['total_count'] = $is_collection ? $data->getTotalCount() : 1;
    }
    $serializer = $this->serializer;
    $normalizer_values = array_map(function ($entity) use ($format, $context, $serializer) {
      return $serializer->normalize($entity, $format, $context);
    }, $entities);

    $link_context = [
      'link_manager' => $this->linkManager,
      'has_next_page' => $context['has_next_page'],
    ];

    if ($include_count) {
      $link_context['total_count'] = $context['total_count'];
    }

    return new JsonApiDocumentTopLevelNormalizerValue($normalizer_values, $context, $link_context, $is_collection);
  }

  /**
   * Expand the context information based on the current request context.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to get the URL params from to expand the context.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type to translate to internal fields.
   *
   * @return array
   *   The expanded context.
   */
  protected function expandContext(Request $request, ResourceType $resource_type) {
    // Translate ALL the includes from the public field names to the internal.
    $includes = array_filter(explode(',', $request->query->get('include')));
    // The primary resource type for 'related' routes is different than the
    // primary resource type of individual and relationship routes and is
    // determined by the relationship field name.
    $related = $request->get('_on_relationship') ? FALSE : $request->get('related');
    $public_includes = array_map(function ($include) use ($resource_type, $related) {
      $trimmed = trim($include);
      // If the request is a related route, prefix the path with the related
      // field name so that the path can be resolved from the base resource
      // type. Then, remove it after the path is resolved.
      $path_parts = explode('.', $related ? "{$related}.{$trimmed}" : $trimmed);
      return array_map(function ($resolved) use ($related) {
        return implode('.', $related ? array_slice($resolved, 1) : $resolved);
      }, FieldResolver::resolveInternalIncludePath($resource_type, $path_parts));
    }, $includes);
    // Flatten the resolved possible include paths.
    $public_includes = array_reduce($public_includes, 'array_merge', []);
    // Build the expanded context.
    $context = [
      'account' => NULL,
      'sparse_fieldset' => NULL,
      'resource_type' => NULL,
      'include' => $public_includes,
      'expanded' => TRUE,
    ];
    if ($request->query->get('fields')) {
      $context['sparse_fieldset'] = array_map(function ($item) {
        return explode(',', $item);
      }, $request->query->get('fields'));
    }

    return $context;
  }

  /**
   * Performs mimimal validation of the document.
   */
  protected static function validateRequestBody(array $document) {
    // Ensure that the relationships key was not placed in the top level.
    if (isset($document['relationships']) && !empty($document['relationships'])) {
      throw new BadRequestHttpException("Found \"relationships\" within the document's top level. The \"relationships\" key must be within resource object.");
    }
    // Ensure that the resource object contains the "type" key.
    if (!isset($document['data']['type'])) {
      throw new BadRequestHttpException("Resource object must include a \"type\".");
    }
    // Ensure that the client provided ID is a valid UUID.
    if (isset($document['data']['id']) && !Uuid::isValid($document['data']['id'])) {
      // This should be a 422 response, but the JSON API specification dictates
      // a 403 Forbidden response. We follow the specification.
      throw new EntityAccessDeniedHttpException(NULL, AccessResult::forbidden(), '/data/id', 'IDs should be properly generated and formatted UUIDs as described in RFC 4122.');
    }
  }

}
