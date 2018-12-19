<?php

namespace Drupal\schemata_json_schema\Normalizer\hal;

use Drupal\schemata_json_schema\Normalizer\json\SchemataSchemaNormalizer as JsonSchemataSchemaNormalizer;

/**
 * Extends the base SchemataSchema normalizer for JSON with HAL+JSON elements.
 *
 * The main distinction between HAL+JSON and HAL is the addition of _links for
 * hyperlink relations and _embedded to inline partial or complete examples of
 * the related entities. Therefore HAL serialization shares many of the same
 * Normalizer classes as JSON, except in this "entrypoint" normalizer and the
 * handling of references.
 */
class SchemataSchemaNormalizer extends JsonSchemataSchemaNormalizer {

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
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    // Create the array of normalized fields, starting with the URI.
    /* @var $entity \Drupal\schemata\Schema\SchemaInterface */
    $normalized = parent::normalize($entity, $format, $context);

    // HAL link schema definitions based on HyperSchema.org.
    // @see http://hyperschema.org/mediatypes/hal
    $items = [];
    if (!empty($normalized['properties']['_links'])) {
      $items = $normalized['properties']['_links'];
    }
    $items['self'] = [
      '$ref' => '#/definitions/linkObject',
    ];
    $items['type'] = [
      '$ref' => '#/definitions/linkObject',
    ];

    $normalized['properties']['_links'] = [
      'title' => 'HAL Links',
      'description' => 'Object of links with the rels as the keys',
      'type' => 'object',
      'properties' => $items,
    ];

    if (!empty($normalized['properties']['_embedded'])) {
      $items = $normalized['properties']['_embedded'];
      $normalized['properties']['_embedded'] = [
        'title' => 'HAL Embedded Resource',
        'description' => 'An embedded HAL resource',
        'type' => 'object',
        'properties' => $items,
      ];
    }

    $normalized['definitions']['linkArray'] = [
      'title' => 'HAL Link Array',
      'description' => 'An array of linkObjects of the same link relation',
      'type' => 'array',
      'items' => [
        '$ref' => '#/definitions/linkObject',
      ],
    ];

    // Drupal core does not currently use several HAL link attributes.
    // If they are added entries should be added here.
    $normalized['definitions']['linkObject'] = [
      'title' => 'HAL Link Object',
      'description' => 'An object with link information.',
      'type' => 'object',
      'properties' => [
        'name' => [
          'title' => 'Name',
          'description' => 'Name of a resource, link, action, etc.',
          'type' => 'string',
        ],
        'title' => [
          'title' => 'Title',
          'description' => 'A title for a resource, link, action, etc.',
          'type' => 'string',
        ],
        'href' => [
          'title' => 'HREF',
          'description' => 'A hyperlink URL.',
          'type' => 'string',
          'format' => 'uri',
        ],
      ],
      'required' => [
        'href',
      ],
    ];

    return $normalized;
  }

}
