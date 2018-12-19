<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests JSON API multilingual support.
 *
 * @group jsonapi
 * @group legacy
 *
 * @internal
 */
class JsonApiFunctionalMultilingualTest extends JsonApiFunctionalTestBase {

  public static $modules = [
    'basic_auth',
    'jsonapi',
    'serialization',
    'node',
    'image',
    'taxonomy',
    'link',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $language = ConfigurableLanguage::createFromLangcode('ca');
    $language->save();

    // In order to reflect the changes for a multilingual site in the container
    // we have to rebuild it.
    $this->rebuildContainer();

    \Drupal::configFactory()->getEditable('language.negotiation')
      ->set('url.prefixes.ca', 'ca')
      ->save();
  }

  /**
   * Tests reading multilingual content.
   */
  public function testReadMultilingual() {
    $this->createDefaultContent(5, 5, TRUE, TRUE, static::IS_MULTILINGUAL, FALSE);

    // Test reading an individual entity.
    $output = Json::decode($this->drupalGet('/ca/jsonapi/node/article/' . $this->nodes[0]->uuid(), ['query' => ['include' => 'field_tags,field_image']]));
    $this->assertEquals($this->nodes[0]->getTranslation('ca')->getTitle(), $output['data']['attributes']['title']);

    $included_tags = array_filter($output['included'], function ($entry) {
      return $entry['type'] === 'taxonomy_term--tags';
    });
    $tag_name = $this->nodes[0]->get('field_tags')->entity
      ->getTranslation('ca')->getName();
    // TODO figure out how to fetcht the alt text of an image.
    $this->assertEquals($tag_name, reset($included_tags)['attributes']['name']);

    $output = Json::decode($this->drupalGet('/ca/jsonapi/node/article/' . $this->nodes[0]->uuid()));
    $this->assertEquals($this->nodes[0]->getTranslation('ca')->getTitle(), $output['data']['attributes']['title']);

    // Test reading a collection of entities.
    $output = Json::decode($this->drupalGet('/ca/jsonapi/node/article'));
    $this->assertEquals($this->nodes[0]->getTranslation('ca')->getTitle(), $output['data'][0]['attributes']['title']);
  }

}
