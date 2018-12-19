<?php

namespace Drupal\Tests\schemata\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\schemata\SchemaUrl;
use Drupal\Tests\BrowserTestBase;
use League\JsonReference\Dereferencer;

/**
 * Sets up functional testing for Schemata.
 */
class SchemataBrowserTestBase extends BrowserTestBase {

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Schema Factory.
   *
   * @var \Drupal\schemata\SchemaFactory
   */
  protected $schemaFactory;

  /**
   * Dereferenced Schema Static Cache.
   *
   * @var array
   *
   * @see ::requestSchemaByUrl()
   */
  protected $schemaCache = [];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'field',
    'filter',
    'text',
    'node',
    'taxonomy',
    'serialization',
    'hal',
    'schemata',
    'schemata_json_schema',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->schemaFactory = $this->container->get('schemata.schema_factory');

    if (!NodeType::load('camelids')) {
      // Create a "Camelids" node type.
      NodeType::create([
        'name' => 'Camelids',
        'type' => 'camelids',
      ])->save();
    }

    // Create a "Camelids" vocabulary.
    $vocabulary = Vocabulary::create([
      'name' => 'Camelids',
      'vid' => 'camelids',
    ]);
    $vocabulary->save();

    $entity_types = ['node', 'taxonomy_term'];
    foreach ($entity_types as $entity_type) {
      // Add access-protected field.
      FieldStorageConfig::create([
        'entity_type' => $entity_type,
        'field_name' => 'field_test_' . $entity_type,
        'type' => 'text',
      ])
        ->setCardinality(1)
        ->save();
      FieldConfig::create([
        'entity_type' => $entity_type,
        'field_name' => 'field_test_' . $entity_type,
        'bundle' => 'camelids',
      ])
        ->setLabel('Test field')
        ->setTranslatable(FALSE)
        ->save();
    }
    $this->container->get('router.builder')->rebuild();
    $this->drupalLogin($this->drupalCreateUser(['access schemata data models']));
  }

  /**
   * Retrieve the schema by URL and dereference for use.
   *
   * Dereferencing a schema processes references to external schema documents
   * and prepares it to be used as a validation authority.
   *
   * This will static cache the schema so the same schema resource will not be
   * retrieved and processed more than once per test run.
   *
   * @param string $url
   *   Absolute URL to a JSON Schema resource.
   *
   * @return object
   *   Dereferenced schema object.
   *
   * @todo Evaluate PSR-16 cache support built into Dereferencer.
   *
   * @see http://json-reference.thephpleague.com/caching
   */
  protected function getDereferencedSchema($url) {
    if (empty($this->schemaCache[$url])) {
      $dereferencer = Dereferencer::draft4();
      // By definition of the JSON Schema spec, schemas use this key to refer
      // to the schema to which they conform.
      $this->schemaCache[$url] = $dereferencer->dereference($url);
    }

    return $this->schemaCache[$url];
  }

  /**
   * Requests a Schema via HTTP, ready for session assertions.
   *
   * @param string $format
   *   The described format.
   * @param string $entity_type_id
   *   Then entity type.
   * @param string|null $bundle_id
   *   The bundle name or NULL.
   *
   * @return string
   *   Serialized schema contents.
   */
  protected function getRawSchemaByOptions($format, $entity_type_id, $bundle_id = NULL) {
    $url = SchemaUrl::fromOptions('schema_json', $format, $entity_type_id, $bundle_id)->toString();
    return $this->drupalGet($url);
  }

  /**
   * Requests a dereferenced Schema via HTTP.
   *
   * Dereferencing a schema processes references to external schema documents
   * and prepares it to be used as a validation authority.
   *
   * @param string $format
   *   The described format.
   * @param string $entity_type_id
   *   Then entity type.
   * @param string|null $bundle_id
   *   The bundle name or NULL.
   *
   * @return object
   *   Dereferenced schema object.
   */
  protected function getDereferencedSchemaByOptions($format, $entity_type_id, $bundle_id = NULL) {
    $url = SchemaUrl::fromOptions('schema_json', $format, $entity_type_id, $bundle_id)->toString();
    return $this->requestSchemaByUrl($url);
  }

}
