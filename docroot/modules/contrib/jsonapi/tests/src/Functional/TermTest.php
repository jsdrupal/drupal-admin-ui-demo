<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceResponse;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\rest\Functional\BcTimestampNormalizerUnixTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * JSON API integration test for the "Term" content entity type.
 *
 * @group jsonapi
 */
class TermTest extends ResourceTestBase {

  use BcTimestampNormalizerUnixTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['taxonomy', 'path'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'taxonomy_term';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'taxonomy_term--camelids';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'changed' => NULL,
  ];

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['access content']);
        break;

      case 'POST':
        // @todo Remove this when JSON API requires Drupal 8.5 or newer.
        if (floatval(\Drupal::VERSION) < 8.5) {
          $this->grantPermissionsToTestedRole(['administer taxonomy']);
        }
        $this->grantPermissionsToTestedRole(['create terms in camelids']);
        break;

      case 'PATCH':
        // Grant the 'create url aliases' permission to test the case when
        // the path field is accessible, see
        // \Drupal\Tests\rest\Functional\EntityResource\Node\NodeResourceTestBase
        // for a negative test.
        $this->grantPermissionsToTestedRole(['edit terms in camelids', 'create url aliases']);
        break;

      case 'DELETE':
        $this->grantPermissionsToTestedRole(['delete terms in camelids']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $vocabulary = Vocabulary::load('camelids');
    if (!$vocabulary) {
      // Create a "Camelids" vocabulary.
      $vocabulary = Vocabulary::create([
        'name' => 'Camelids',
        'vid' => 'camelids',
      ]);
      $vocabulary->save();
    }

    // Create a "Llama" taxonomy term.
    $term = Term::create(['vid' => $vocabulary->id()])
      ->setName('Llama')
      ->setDescription("It is a little known fact that llamas cannot count higher than seven.")
      ->setChangedTime(123456789)
      ->set('path', '/llama');
    $term->save();

    return $term;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/taxonomy_term/camelids/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();

    // We test with multiple parent terms, and combinations thereof.
    // @see ::createEntity()
    // @see ::testGetIndividual()
    // @see ::testGetIndividualTermWithParent()
    // @see ::providerTestGetIndividualTermWithParent()
    $parent_term_ids = [];
    for ($i = 0; $i < $this->entity->get('parent')->count(); $i++) {
      $parent_term_ids[$i] = (int) $this->entity->get('parent')[$i]->target_id;
    }

    $expected_parent_normalization = FALSE;
    switch ($parent_term_ids) {
      case [0]:
        $expected_parent_normalization = [
          'data' => [
            [
              'id' => 'virtual',
              'type' => 'taxonomy_term--camelids',
              'meta' => [
                'links' => [
                  'help' => [
                    'href' => 'https://www.drupal.org/docs/8/modules/json-api/core-concepts#virtual',
                    'meta' => [
                      'about' => "Usage and meaning of the 'virtual' resource identifier.",
                    ],
                  ],
                ],
              ],
            ],
          ],
          'links' => [
            'related' => $self_url . '/parent',
            'self' => $self_url . '/relationships/parent',
          ],
        ];
        break;

      case [2]:
        $expected_parent_normalization = [
          'data' => [
            [
              'id' => Term::load(2)->uuid(),
              'type' => 'taxonomy_term--camelids',
            ],
          ],
          'links' => [
            'related' => $self_url . '/parent',
            'self' => $self_url . '/relationships/parent',
          ],
        ];
        break;

      case [0, 2]:
        $expected_parent_normalization = [
          'data' => [
            [
              'id' => 'virtual',
              'type' => 'taxonomy_term--camelids',
              'meta' => [
                'links' => [
                  'help' => [
                    'href' => 'https://www.drupal.org/docs/8/modules/json-api/core-concepts#virtual',
                    'meta' => [
                      'about' => "Usage and meaning of the 'virtual' resource identifier.",
                    ],
                  ],
                ],
              ],
            ],
            [
              'id' => Term::load(2)->uuid(),
              'type' => 'taxonomy_term--camelids',
            ],
          ],
          'links' => [
            'related' => $self_url . '/parent',
            'self' => $self_url . '/relationships/parent',
          ],
        ];
        break;

      case [3, 2]:
        $expected_parent_normalization = [
          'data' => [
            [
              'id' => Term::load(3)->uuid(),
              'type' => 'taxonomy_term--camelids',
            ],
            [
              'id' => Term::load(2)->uuid(),
              'type' => 'taxonomy_term--camelids',
            ],
          ],
          'links' => [
            'related' => $self_url . '/parent',
            'self' => $self_url . '/relationships/parent',
          ],
        ];
        break;
    }

    // @todo Remove this when JSON API requires Drupal 8.6 or newer.
    if (floatval(\Drupal::VERSION) < 8.6) {
      $expected_parent_normalization = [
        'data' => [],
        'links' => [
          'related' => $self_url . '/parent',
          'self' => $self_url . '/relationships/parent',
        ],
      ];
    }

    $document = [
      'jsonapi' => [
        'meta' => [
          'links' => [
            'self' => 'http://jsonapi.org/format/1.0/',
          ],
        ],
        'version' => '1.0',
      ],
      'links' => [
        'self' => $self_url,
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'taxonomy_term--camelids',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'changed' => $this->entity->getChangedTime(),
          // @todo uncomment this in https://www.drupal.org/project/jsonapi/issues/2929932
          /* 'changed' => $this->formatExpectedTimestampItemValues($this->entity->getChangedTime()), */
          'default_langcode' => TRUE,
          'description' => [
            'value' => 'It is a little known fact that llamas cannot count higher than seven.',
            'format' => NULL,
            'processed' => "<p>It is a little known fact that llamas cannot count higher than seven.</p>\n",
          ],
          'langcode' => 'en',
          'name' => 'Llama',
          'path' => [
            'alias' => '/llama',
            'pid' => 1,
            'langcode' => 'en',
          ],
          'tid' => 1,
          'uuid' => $this->entity->uuid(),
          'weight' => 0,
        ],
        'relationships' => [
          'parent' => $expected_parent_normalization,
          'vid' => [
            'data' => [
              'id' => Vocabulary::load('camelids')->uuid(),
              'type' => 'taxonomy_vocabulary--taxonomy_vocabulary',
            ],
            'links' => [
              'related' => $self_url . '/vid',
              'self' => $self_url . '/relationships/vid',
            ],
          ],
        ],
      ],
    ];
    // @todo Remove this when JSON API requires Drupal 8.5 or newer.
    if (floatval(\Drupal::VERSION) < 8.5) {
      unset($document['data']['attributes']['description']['processed']);
    }
    return $document;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedGetRelationshipDocumentData($relationship_field_name, EntityInterface $entity = NULL) {
    $data = parent::getExpectedGetRelationshipDocumentData($relationship_field_name, $entity);
    if ($relationship_field_name === 'parent' && floatval(\Drupal::VERSION) >= 8.6) {
      $data = [
        0 => [
          'id' => 'virtual',
          'type' => 'taxonomy_term--camelids',
          'meta' => [
            'links' => [
              'help' => [
                'href' => 'https://www.drupal.org/docs/8/modules/json-api/core-concepts#virtual',
                'meta' => [
                  'about' => "Usage and meaning of the 'virtual' resource identifier.",
                ],
              ],
            ],
          ],
        ],
      ];
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedRelatedResponses(array $relationship_field_names, array $request_options, EntityInterface $entity = NULL) {
    $responses = parent::getExpectedRelatedResponses($relationship_field_names, $request_options, $entity);
    if ($responses['parent']->getStatusCode() === 404 && floatval(\Drupal::VERSION) >= 8.6) {
      $responses['parent'] = new ResourceResponse([
        'data' => [],
        'jsonapi' => [
          'meta' => [
            'links' => [
              'self' => 'http://jsonapi.org/format/1.0/',
            ],
          ],
          'version' => '1.0',
        ],
        'links' => ['self' => static::getRelatedLink(static::toResourceIdentifier($this->entity), 'parent')],
      ]);
    }
    return $responses;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    return [
      'data' => [
        'type' => 'taxonomy_term--camelids',
        'attributes' => [
          'name' => 'Dramallama',
          'description' => [
            'value' => 'Dramallamas are the coolest camelids.',
            'format' => NULL,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET':
        return "The 'access content' permission is required.";

      case 'POST':
        // @todo Remove this when JSON API requires Drupal 8.5 or newer.
        if (floatval(\Drupal::VERSION) < 8.5) {
          return "The 'administer taxonomy' permission is required.";
        }
        return "The following permissions are required: 'create terms in camelids' OR 'administer taxonomy'.";

      case 'PATCH':
        return "The following permissions are required: 'edit terms in camelids' OR 'administer taxonomy'.";

      case 'DELETE':
        return "The following permissions are required: 'delete terms in camelids' OR 'administer taxonomy'.";

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * Tests PATCHing a term's path.
   *
   * For a negative test, see the similar test coverage for Node.
   *
   * @see \Drupal\Tests\jsonapi\Functional\NodeTest::testPatchPath()
   * @see \Drupal\Tests\rest\Functional\EntityResource\Node\NodeResourceTestBase::testPatchPath()
   */
  public function testPatchPath() {
    $this->setUpAuthorization('GET');
    $this->setUpAuthorization('PATCH');

    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/jsonapi/issues/2878463.
    $url = Url::fromRoute(sprintf('jsonapi.%s.individual', static::$resourceTypeName), [static::$entityTypeId => $this->entity->uuid()]);
    /* $url = $this->entity->toUrl('jsonapi'); */
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    // GET term's current normalization.
    $response = $this->request('GET', $url, $request_options);
    $normalization = Json::decode((string) $response->getBody());

    // Change term's path alias.
    $normalization['data']['attributes']['path']['alias'] .= 's-rule-the-world';

    // Create term PATCH request.
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // PATCH request: 200.
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $updated_normalization = Json::decode((string) $response->getBody());
    $this->assertSame($normalization['data']['attributes']['path']['alias'], $updated_normalization['data']['attributes']['path']['alias']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheTags(array $sparse_fieldset = NULL) {
    // @todo Remove this when JSON API requires Drupal 8.5 or newer.
    if (floatval(\Drupal::VERSION) < 8.5) {
      return parent::getExpectedCacheTags($sparse_fieldset);
    }

    $tags = parent::getExpectedCacheTags($sparse_fieldset);
    if ($sparse_fieldset === NULL || in_array('description', $sparse_fieldset)) {
      $tags = Cache::mergeTags($tags, ['config:filter.format.plain_text', 'config:filter.settings']);
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts(array $sparse_fieldset = NULL) {
    // @todo Remove this when JSON API requires Drupal 8.5 or newer.
    if (floatval(\Drupal::VERSION) < 8.5) {
      return parent::getExpectedCacheContexts($sparse_fieldset);
    }

    $contexts = parent::getExpectedCacheContexts($sparse_fieldset);
    if ($sparse_fieldset === NULL || in_array('description', $sparse_fieldset)) {
      $contexts = Cache::mergeContexts($contexts, ['languages:language_interface', 'theme']);
    }
    return $contexts;
  }

  /**
   * Tests GETting a term with a parent term other than the default <root> (0).
   *
   * @see ::getExpectedNormalizedEntity()
   *
   * @dataProvider providerTestGetIndividualTermWithParent
   */
  public function testGetIndividualTermWithParent(array $parent_term_ids) {
    if (floatval(\Drupal::VERSION) < 8.6) {
      $this->markTestSkipped('The "parent" field on terms is only available for normalization in Drupal 8.6 and later.');
      return;
    }

    // Create all possible parent terms.
    Term::create(['vid' => Vocabulary::load('camelids')->id()])
      ->setName('Lamoids')
      ->save();
    Term::create(['vid' => Vocabulary::load('camelids')->id()])
      ->setName('Wimoids')
      ->save();

    // Modify the entity under test to use the provided parent terms.
    $this->entity->set('parent', $parent_term_ids)->save();

    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/jsonapi/issues/2878463.
    $url = Url::fromRoute(sprintf('jsonapi.%s.individual', static::$resourceTypeName), [static::$entityTypeId => $this->entity->uuid()]);
    /* $url = $this->entity->toUrl('jsonapi'); */
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());
    $this->setUpAuthorization('GET');
    $response = $this->request('GET', $url, $request_options);
    $this->assertSameDocument($this->getExpectedDocument(), Json::decode($response->getBody()));
  }

  /**
   * Data provider for ::testGetIndividualTermWithParent().
   */
  public function providerTestGetIndividualTermWithParent() {
    return [
      'root parent: [0] (= no parent)' => [
        [0],
      ],
      'non-root parent: [2]' => [
        [2],
      ],
      'multiple parents: [0,2] (root + non-root parent)' => [
        [0, 2],
      ],
      'multiple parents: [3,2] (both non-root parents)' => [
        [3, 2],
      ],
    ];
  }

}
