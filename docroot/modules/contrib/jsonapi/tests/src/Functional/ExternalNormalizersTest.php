<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Asserts external normalizers are handled as expected by the JSON API module.
 *
 * @see jsonapi.normalizers
 *
 * @group jsonapi
 */
class ExternalNormalizersTest extends BrowserTestBase {

  /**
   * The original value for the test field.
   *
   * @var string
   */
  const VALUE_ORIGINAL = 'Llamas are super awesome!';

  /**
   * The expected overridden value for the test field.
   *
   * @see \Drupal\jsonapi_test_field_type\Normalizer\StringNormalizer
   * @see \Drupal\jsonapi_test_data_type\Normalizer\StringNormalizer
   */
  const VALUE_OVERRIDDEN = 'Llamas are NOT awesome!';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi',
    'entity_test',
  ];

  /**
   * The test entity.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // This test is not about access control at all, so allow anonymous users to
    // view the test entities.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('view test entity')
      ->save();

    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'type' => 'string',
      'entity_type' => 'entity_test',
    ])
      ->save();
    FieldConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])
      ->save();

    $this->entity = EntityTest::create([
      'name' => 'Llama',
      'type' => 'entity_test',
      'field_test' => static::VALUE_ORIGINAL,
    ]);
    $this->entity->save();
  }

  /**
   * Tests a format-agnostic normalizer.
   *
   * @param string $test_module
   *   The test module to install, which comes with a high-priority normalizer.
   * @param string $expected_value_jsonapi_normalization
   *   The expected JSON API normalization of the tested field. Must be either
   *   - static::VALUE_ORIGINAL (normalizer IS NOT expected to override)
   *   - static::VALUE_OVERRIDDEN (normalizer IS expected to override)
   *
   * @dataProvider providerTestFormatAgnosticNormalizers
   */
  public function testFormatAgnosticNormalizers($test_module, $expected_value_jsonapi_normalization) {
    assert(in_array($expected_value_jsonapi_normalization, [static::VALUE_ORIGINAL, static::VALUE_OVERRIDDEN], TRUE));

    // Asserts the entity contains the value we set.
    $this->assertSame(static::VALUE_ORIGINAL, $this->entity->field_test->value);

    // Asserts normalizing the entity using core's 'serializer' service DOES
    // yield the value we set.
    $core_normalization = $this->container->get('serializer')->normalize($this->entity);
    $this->assertSame(static::VALUE_ORIGINAL, $core_normalization['field_test'][0]['value']);

    // Install test module that contains a high-priority alternative normalizer.
    $this->container->get('module_installer')->install([$test_module]);
    $this->rebuildContainer();

    // Asserts normalizing the entity using core's 'serializer' service DOES NOT
    // ANYMORE yield the value we set.
    $core_normalization = $this->container->get('serializer')->normalize($this->entity);
    $this->assertSame(static::VALUE_OVERRIDDEN, $core_normalization['field_test'][0]['value']);

    // Asserts that this does NOT affect the JSON API normalization.
    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/jsonapi/issues/2878463.
    $url = Url::fromRoute('jsonapi.entity_test--entity_test.individual', ['entity_test' => $this->entity->uuid()]);
    /* $url = $this->entity->toUrl('jsonapi'); */
    $client = $this->getSession()->getDriver()->getClient()->getClient();
    $response = $client->request('GET', $url->setAbsolute(TRUE)->toString());
    $document = Json::decode((string) $response->getBody());
    $this->assertSame($expected_value_jsonapi_normalization, $document['data']['attributes']['field_test']);
  }

  /**
   * Data provider.
   *
   * @return array
   *   Test cases.
   */
  public function providerTestFormatAgnosticNormalizers() {
    return [
      'Format-agnostic @FieldType-level normalizers SHOULD NOT be able to affect the JSON API normalization' => [
        // \Drupal\jsonapi_test_field_type\Normalizer\StringNormalizer::normalize()
        'jsonapi_test_field_type',
        static::VALUE_ORIGINAL,
      ],
      'Format-agnostic @DataType-level normalizers SHOULD be able to affect the JSON API normalization' => [
        // \Drupal\jsonapi_test_data_type\Normalizer\StringNormalizer::normalize()
        'jsonapi_test_data_type',
        static::VALUE_OVERRIDDEN,
      ],
    ];
  }

}
