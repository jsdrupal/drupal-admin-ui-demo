<?php

namespace Drupal\schemata_json_schema\Normalizer\hal;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\schemata_json_schema\Normalizer\json\DataReferenceDefinitionNormalizer as JsonDataReferenceDefinitionNormalizer;
use Drupal\schemata\SchemaUrl;
use Drupal\hal\LinkManager\LinkManagerInterface;

/**
 * Normalizer for Entity References in HAL+JSON style.
 */
class DataReferenceDefinitionNormalizer extends JsonDataReferenceDefinitionNormalizer {

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $format = 'schema_json';

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $describedFormat = 'hal_json';

  /**
   * The hypermedia link manager.
   *
   * @var \Drupal\hal\LinkManager\LinkManagerInterface
   */
  protected $linkManager;

  /**
   * Constructs an DataReferenceDefinitionNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager.
   * @param \Drupal\hal\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LinkManagerInterface $link_manager) {
    parent::__construct($entity_type_manager);
    $this->linkManager = $link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    /* @var $entity \Drupal\Core\TypedData\DataReferenceDefinitionInterface */
    if (!$this->validateEntity($entity)) {
      return [];
    }

    // Collect data about the reference field.
    $parentProperty = $this->extractPropertyData($context['parent'], $context);
    $property = $this->extractPropertyData($entity, $context);
    $target_type = $entity->getConstraint('EntityType');
    $target_bundles = isset($context['settings']['handler_settings']['target_bundles']) ?
      $context['settings']['handler_settings']['target_bundles'] : [];

    // Build the relation URI, which is used as the property key.
    $field_uri = $this->linkManager->getRelationUri(
      $context['entityTypeId'],
      // Drupal\Core\Entity\Entity::bundle() returns Entity Type ID by default.
      isset($context['bundleId']) ? $context['bundleId'] : $context['entityTypeId'],
      $context['name'],
      $context
    );

    // From the root of the schema object, build out object references.
    $normalized = [
      '_links' => [
        $field_uri => [
          '$ref' => '#/definitions/linkArray',
        ],
      ],
      '_embedded' => [
        $field_uri => [
          'type' => 'array',
          'items' => [],
        ],
      ],
    ];

    // Add title and description to relation definition.
    if (isset($parentProperty['title'])) {
      $normalized['_links'][$field_uri]['title'] = $parentProperty['title'];
      $normalized['_embedded'][$field_uri]['title'] = $parentProperty['title'];
    }
    if (isset($parentProperty['description'])) {
      $normalized['_links'][$field_uri]['description'] = $parentProperty['description'];
    }

    // Add Schema resource references.
    $item = &$normalized['_embedded'][$field_uri]['items'];
    if (empty($target_bundles)) {
      $generated_url = SchemaUrl::fromOptions(
        $this->format,
        $this->describedFormat,
        $target_type
      )->toString(TRUE);
      $item['$ref'] = $generated_url->getGeneratedUrl();
    }
    elseif (count($target_bundles) == 1) {
      $generated_url = SchemaUrl::fromOptions(
        $this->format,
        $this->describedFormat,
        $target_type,
        reset($target_bundles)
      )->toString(TRUE);
      $item['$ref'] = $generated_url->getGeneratedUrl();
    }
    elseif (count($target_bundles) > 1) {
      $refs = [];
      foreach ($target_bundles as $bundle) {
        $generated_url = SchemaUrl::fromOptions(
          $this->format,
          $this->describedFormat,
          $target_type,
          $bundle
        )->toString(TRUE);
        $refs[] = [
          '$ref' => $generated_url->getGeneratedUrl(),
        ];
      }

      $item['anyOf'] = $refs;
    }

    return ['properties' => $normalized];
  }

}
