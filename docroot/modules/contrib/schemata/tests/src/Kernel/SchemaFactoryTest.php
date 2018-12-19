<?php

namespace Drupal\Tests\schemata\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\schemata\Schema\SchemaInterface;
use Drupal\schemata\Schema\NodeSchema;

/**
 * Tests the Schema Factory service.
 *
 * @coversDefaultClass \Drupal\schemata\SchemaFactory
 * @group Schemata
 * @group SchemataCore
 */
class SchemaFactoryTest extends KernelTestBase {

  /**
   * Schema Factory.
   *
   * @var \Drupal\schemata\SchemaFactory
   */
  protected $factory;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'schemata',
    'field',
    'node',
    'serialization',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    // Add the additional table schemas.
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);

    $this->nodeType = NodeType::create([
      'type' => 'article',
    ]);
    $this->nodeType->save();

    $this->factory = \Drupal::service('schemata.schema_factory');
  }

  /**
   * @covers ::create
   */
  public function testCreateNodeBaseSchema() {
    $schema = $this->factory->create('node');
    $this->assertInstanceOf(SchemaInterface::class, $schema);
    $this->assertNotInstanceOf(NodeSchema::class, $schema);
    $this->assertSchemaHasNoBundle($schema);
    $this->assertSchemaHasTitle($schema);
    $this->assertSchemaHasDescription($schema);
    $this->assertSchemaHasProperties($schema);
  }

  /**
   * @covers ::create
   */
  public function testCreateNodeArticleSchema() {
    $schema = $this->factory->create('node', 'article');
    $this->assertInstanceOf(SchemaInterface::class, $schema);
    $this->assertInstanceOf(NodeSchema::class, $schema);
    $this->assertSchemaHasBundle($schema, 'article');
    $this->assertSchemaHasTitle($schema);
    $this->assertSchemaHasDescription($schema);
    $this->assertSchemaHasProperties($schema);
  }

  /**
   * @covers ::create
   */
  public function testCreateUserSchema() {
    $schema = $this->factory->create('user');
    $this->assertInstanceOf(SchemaInterface::class, $schema);
    $this->assertNotInstanceOf(NodeSchema::class, $schema);
    $this->assertSchemaHasNoBundle($schema);
    $this->assertSchemaHasTitle($schema);
    $this->assertSchemaHasDescription($schema);
    $this->assertSchemaHasProperties($schema);
  }

  /**
   * @covers ::create
   */
  public function testInvalidEntityOnCreate() {
    $schema = $this->factory->create('gastropod');
    $this->assertEmpty($schema, 'Schemata should not produce a schema for non-existant entity types.');
    $schema = $this->factory->create('node', 'gastropod');
    $this->assertEmpty($schema, 'Schemata should not produce a schema for non-existant bundles.');
  }

  /**
   * @covers ::getSourceEntityPlugin
   */
  public function testInvalidEntityOnGetPlugin() {
    $this->setExpectedException('\Drupal\Component\Plugin\Exception\PluginNotFoundException');
    $this->factory->getSourceEntityPlugin('gastropod');
  }

  /**
   * @covers ::create
   */
  public function testConfigEntityOnCreate() {
    $schema = $this->factory->create('node_type');
    $this->assertEmpty($schema, 'Schemata does not support Config entities.');
  }

  /**
   * @covers ::getSourceEntityPlugin
   */
  public function testConfigEntityOnGetPlugin() {
    $this->setExpectedException('\InvalidArgumentException');
    $this->factory->getSourceEntityPlugin('node_type');
  }

  /**
   * Assert the schema has a title.
   *
   * @param \Drupal\schemata\Schema\SchemaInterface $schema
   *   Schema to evaluate.
   */
  protected function assertSchemaHasTitle(SchemaInterface $schema) {
    $this->assertNotEmpty($schema->getMetadata()['title']);
  }

  /**
   * Assert the schema has a description.
   *
   * @param \Drupal\schemata\Schema\SchemaInterface $schema
   *   Schema to evaluate.
   */
  protected function assertSchemaHasDescription(SchemaInterface $schema) {
    $this->assertNotEmpty($schema->getMetadata()['description']);
  }

  /**
   * Assert the schema has at least one property.
   *
   * More extensive property analysis would be redundant, as the only way we
   * could meaningfully check would be to execute the same code. This confirms
   * the SchemaFactory was able to derive properties at all and get them into
   * the schema object.
   *
   * @param \Drupal\schemata\Schema\SchemaInterface $schema
   *   Schema to evaluate.
   */
  protected function assertSchemaHasProperties(SchemaInterface $schema) {
    $this->assertGreaterThanOrEqual(1, count($schema->getProperties()));
  }

  /**
   * Assert the schema has the specified bundle.
   *
   * @param \Drupal\schemata\Schema\SchemaInterface $schema
   *   Schema to evaluate.
   * @param string $bundle
   *   Bundle we expect the Schema to self-declare.
   */
  protected function assertSchemaHasBundle(SchemaInterface $schema, $bundle) {
    $this->assertEquals($bundle, $schema->getBundleId());
  }

  /**
   * Assert the schema has no entity bundle.
   *
   * @param \Drupal\schemata\Schema\SchemaInterface $schema
   *   Schema to evaluate.
   */
  protected function assertSchemaHasNoBundle(SchemaInterface $schema) {
    $this->assertEmpty($schema->getBundleId());
  }

}
