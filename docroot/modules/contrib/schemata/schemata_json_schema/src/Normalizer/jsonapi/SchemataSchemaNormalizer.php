<?php

namespace Drupal\schemata_json_schema\Normalizer\jsonapi;

use Drupal\schemata\Schema\SchemaInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\schemata\SchemaUrl;

/**
 * Primary normalizer for SchemaInterface objects.
 */
class SchemataSchemaNormalizer extends JsonApiNormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\schemata\Schema\SchemaInterface';

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    /* @var $entity \Drupal\schemata\Schema\SchemaInterface */
    $generated_url = SchemaUrl::fromSchema($this->format, $this->describedFormat, $entity)
      ->toString(TRUE);
    // Create the array of normalized fields, starting with the URI.
    /** @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository */
    $resource_type_repository = \Drupal::service('jsonapi.resource_type.repository');
    $resource_type = $resource_type_repository->get(
      $entity->getEntityTypeId(),
      $entity->getBundleId() ?: $entity->getEntityTypeId()
    );
    $normalized = [
      '$schema' => 'http://json-schema.org/draft-04/schema#',
      'id' => $generated_url->getGeneratedUrl(),
      'type' => 'object',
      'required' => ['data'],
      'additionalProperties' => FALSE,
      'properties' => [
        'data' => [
          'type' => 'object',
          'properties' => [
            'type' => [
              'type' => 'string',
              'title' => 'type',
              'description' => t('Resource type'),
              'enum' => [$resource_type->getTypeName()]
            ],
            'id' => [
              'type' => 'string',
              'title' => t('Resource ID'),
              'format' => 'uuid',
              'maxLength' => 128,
            ],
          ],
        ]
      ]
    ];

    // Stash schema request parameters.
    $context['entityTypeId'] = $entity->getEntityTypeId();
    $context['bundleId'] = $entity->getBundleId();

    // Retrieve 'properties' and possibly 'required' nested arrays.
    $properties = $this->normalizeProperties(
      $this->getProperties($entity, $format, $context),
      $format,
      $context
    );
    $properties = ['properties' => ['data' => $properties]];
    $links = [
      'properties' => [
        'links' => [
          'type' => 'object',
          'description' => t('Entity links'),
          'properties' => [
            'self' => [
              'type' => 'string',
              'format' => 'uri',
              'description' => t('The absolute link to this entity.'),
            ],
          ],
        ],
      ],
    ];
    $links = ['properties' => ['data' => $links]];
    return NestedArray::mergeDeep($normalized, $entity->getMetadata(), $properties, $links);
  }

  /**
   * Identify properties of the data definition to normalize.
   *
   * This allow subclasses of the normalizer to build white or blacklisting
   * functionality on what will be included in the serialized schema. The JSON
   * Schema serializer already has logic to drop any properties that are empty
   * values after processing, but this allows cleaner, centralized logic.
   *
   * @param \Drupal\schemata\Schema\SchemaInterface $entity
   *   The Schema object whose properties the serializer will present.
   * @param string $format
   *   The serializer format. Defaults to NULL.
   * @param array $context
   *   The current serializer context.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The DataDefinitions to be processed.
   */
  protected static function getProperties(SchemaInterface $entity, $format = NULL, array $context = []) {
    return $entity->getProperties();
  }

}
