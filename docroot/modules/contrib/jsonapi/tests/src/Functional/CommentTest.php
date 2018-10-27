<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\rest\Functional\BcTimestampNormalizerUnixTestTrait;
use Drupal\user\Entity\User;
use GuzzleHttp\RequestOptions;

/**
 * JSON API integration test for the "Comment" content entity type.
 *
 * @group jsonapi
 */
class CommentTest extends ResourceTestBase {

  use BcTimestampNormalizerUnixTestTrait;
  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['comment', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'comment';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'comment--comment';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'status' => "The 'administer comments' permission is required.",
    // @todo These are relationships, and cannot be tested in the same way. Fix in https://www.drupal.org/project/jsonapi/issues/2939810.
    // 'pid' => NULL,
    // 'entity_id' => NULL,
    // 'uid' => NULL,
    'name' => "The 'administer comments' permission is required.",
    'homepage' => "The 'administer comments' permission is required.",
    'created' => "The 'administer comments' permission is required.",
    'changed' => NULL,
    'thread' => NULL,
    'entity_type' => NULL,
    'field_name' => NULL,
  ];

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\comment\CommentInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['access comments', 'view test entity']);
        break;

      case 'POST':
        $this->grantPermissionsToTestedRole(['post comments']);
        break;

      case 'PATCH':
        $this->grantPermissionsToTestedRole(['edit own comments']);
        break;

      case 'DELETE':
        $this->grantPermissionsToTestedRole(['administer comments']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "bar" bundle for the "entity_test" entity type and create.
    $bundle = 'bar';
    entity_test_create_bundle($bundle, NULL, 'entity_test');

    // Create a comment field on this bundle.
    $this->addDefaultCommentField('entity_test', 'bar', 'comment');

    // Create a "Camelids" test entity that the comment will be assigned to.
    $commented_entity = EntityTest::create([
      'name' => 'Camelids',
      'type' => 'bar',
    ]);
    $commented_entity->save();

    // Create a "Llama" comment.
    $comment = Comment::create([
      'comment_body' => [
        'value' => 'The name "llama" was adopted by European settlers from native Peruvians.',
        'format' => 'plain_text',
      ],
      'entity_id' => $commented_entity->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'comment',
    ]);
    $comment->setSubject('Llama')
      ->setOwnerId($this->account->id())
      ->setPublished(TRUE)
      ->setCreatedTime(123456789)
      ->setChangedTime(123456789);
    $comment->save();

    return $comment;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/comment/comment/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    $author = User::load($this->entity->getOwnerId());
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
        'type' => 'comment--comment',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'cid' => 1,
          'created' => 123456789,
          // @todo uncomment this in https://www.drupal.org/project/jsonapi/issues/2929932
          /* 'created' => $this->formatExpectedTimestampItemValues(123456789), */
          'changed' => $this->entity->getChangedTime(),
          // @todo uncomment this in https://www.drupal.org/project/jsonapi/issues/2929932
          /* 'changed' => $this->formatExpectedTimestampItemValues($this->entity->getChangedTime()), */
          'comment_body' => [
            'value' => 'The name "llama" was adopted by European settlers from native Peruvians.',
            'format' => 'plain_text',
            'processed' => "<p>The name &quot;llama&quot; was adopted by European settlers from native Peruvians.</p>\n",
          ],
          'default_langcode' => TRUE,
          'entity_type' => 'entity_test',
          'field_name' => 'comment',
          'homepage' => NULL,
          'langcode' => 'en',
          'name' => NULL,
          'status' => TRUE,
          'subject' => 'Llama',
          'thread' => '01/',
          'uuid' => $this->entity->uuid(),
        ],
        'relationships' => [
          'uid' => [
            'data' => [
              'id' => $author->uuid(),
              'type' => 'user--user',
            ],
            'links' => [
              'related' => $self_url . '/uid',
              'self' => $self_url . '/relationships/uid',
            ],
          ],
          'comment_type' => [
            'data' => [
              'id' => CommentType::load('comment')->uuid(),
              'type' => 'comment_type--comment_type',
            ],
            'links' => [
              'related' => $self_url . '/comment_type',
              'self' => $self_url . '/relationships/comment_type',
            ],
          ],
          'entity_id' => [
            'data' => [
              'id' => EntityTest::load(1)->uuid(),
              'type' => 'entity_test--bar',
            ],
            'links' => [
              'related' => $self_url . '/entity_id',
              'self' => $self_url . '/relationships/entity_id',
            ],
          ],
          'pid' => [
            'data' => NULL,
            'links' => [
              'related' => $self_url . '/pid',
              'self' => $self_url . '/relationships/pid',
            ],
          ],
        ],
      ],
    ];
    // @todo Remove this when JSON API requires Drupal 8.5 or newer.
    if (floatval(\Drupal::VERSION) < 8.5) {
      unset($document['data']['attributes']['comment_body']['processed']);
    }
    return $document;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    return [
      'data' => [
        'type' => 'comment--comment',
        'attributes' => [
          'entity_type' => 'entity_test',
          'field_name' => 'comment',
          'subject' => 'Dramallama',
          'comment_body' => [
            'value' => 'Llamas are awesome.',
            'format' => 'plain_text',
          ],
        ],
        'relationships' => [
          'entity_id' => [
            'data' => [
              'type' => 'entity_test--bar',
              'id' => EntityTest::load(1)->uuid(),
            ],
          ],
        ],
      ],
    ];
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
    if ($sparse_fieldset === NULL || in_array('comment_body', $sparse_fieldset)) {
      $tags = Cache::mergeTags($tags, ['config:filter.format.plain_text']);
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
    if ($sparse_fieldset === NULL || in_array('comment_body', $sparse_fieldset)) {
      $contexts = Cache::mergeContexts($contexts, ['languages:language_interface', 'theme']);
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET';
        return "The 'access comments' permission is required and the comment must be published.";

      case 'POST';
        return "The 'post comments' permission is required.";

      case 'PATCH':
        // @todo Make this unconditional when JSON API requires Drupal 8.6 or newer.
        if (floatval(\Drupal::VERSION) >= 8.6) {
          return "The 'edit own comments' permission is required, the user must be the comment author, and the comment must be published.";
        }

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * Tests POSTing a comment without critical base fields.
   *
   * Note that testPostIndividual() is testing with the most minimal
   * normalization possible: the one returned by ::getNormalizedPostEntity().
   *
   * But Comment entities have some very special edge cases:
   * - base fields that are not marked as required in
   *   \Drupal\comment\Entity\Comment::baseFieldDefinitions() yet in fact are
   *   required.
   * - base fields that are marked as required, but yet can still result in
   *   validation errors other than "missing required field".
   */
  public function testPostIndividualDxWithoutCriticalBaseFields() {
    // @codingStandardsIgnoreStart
    $this->setUpAuthorization('POST');

    $url = Url::fromRoute(sprintf('jsonapi.%s.collection', static::$resourceTypeName));
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $remove_field = function(array $normalization, $type, $attribute_name) {
      unset($normalization['data'][$type][$attribute_name]);
      return $normalization;
    };

    // DX: 422 when missing 'entity_type' field.
    $request_options[RequestOptions::BODY] = Json::encode($remove_field($this->getPostDocument(), 'attributes',  'entity_type'));
    $response = $this->request('POST', $url, $request_options);
    // @todo Uncomment, remove next line in https://www.drupal.org/node/2820364.
    $this->assertResourceErrorResponse(500, 'The "" entity type does not exist.', $response);
    // $this->assertResourceErrorResponse(422, 'Unprocessable Entity', 'entity_type: This value should not be null.', $response);

    // DX: 422 when missing 'entity_id' field.
    $request_options[RequestOptions::BODY] = Json::encode($remove_field($this->getPostDocument(), 'relationships', 'entity_id'));
    // @todo Remove the try/catch in favor of the two commented lines in
    // https://www.drupal.org/node/2820364.
    try {
      $response = $this->request('POST', $url, $request_options);
      // This happens on DrupalCI.
      $this->assertSame(500, $response->getStatusCode());
    }
    catch (\Exception $e) {
      // This happens on local development environments
      $this->assertSame("Error: Call to a member function get() on null\nDrupal\\comment\\Plugin\\Validation\\Constraint\\CommentNameConstraintValidator->getAnonymousContactDetailsSetting()() (Line: 96)\n", $e->getMessage());
    }
    // $response = $this->request('POST', $url, $request_options);
    // $this->assertResourceErrorResponse(422, 'Unprocessable Entity', 'entity_id: This value should not be null.', $response);

    // DX: 422 when missing 'field_name' field.
    $request_options[RequestOptions::BODY] = Json::encode($remove_field($this->getPostDocument(), 'attributes', 'field_name'));
    $response = $this->request('POST', $url, $request_options);
    // @todo Uncomment, remove next line in https://www.drupal.org/node/2820364.
    $this->assertResourceErrorResponse(500, 'Field  is unknown.', $response);
    // $this->assertResourceErrorResponse(422, 'Unprocessable Entity', 'field_name: This value should not be null.', $response);
    // @codingStandardsIgnoreEnd
  }

  /**
   * Tests POSTing a comment with and without 'skip comment approval'.
   */
  public function testPostIndividualSkipCommentApproval() {
    $this->setUpAuthorization('POST');

    // Create request.
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());
    $request_options[RequestOptions::BODY] = Json::encode($this->getPostDocument());

    $url = Url::fromRoute('jsonapi.comment--comment.collection');

    // Status should be FALSE when posting as anonymous.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(201, FALSE, $response);
    $this->assertFalse(Json::decode((string) $response->getBody())['data']['attributes']['status']);
    $this->assertFalse($this->entityStorage->loadUnchanged(2)->isPublished());

    // Grant anonymous permission to skip comment approval.
    $this->grantPermissionsToTestedRole(['skip comment approval']);

    // Status must be TRUE when posting as anonymous and skip comment approval.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(201, FALSE, $response);
    $this->assertTrue(Json::decode((string) $response->getBody())['data']['attributes']['status']);
    $this->assertTrue($this->entityStorage->loadUnchanged(3)->isPublished());
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessCacheability() {
    // @see \Drupal\comment\CommentAccessControlHandler::checkAccess()
    return parent::getExpectedUnauthorizedAccessCacheability()
      ->addCacheTags(['comment:1']);
  }

  /**
   * {@inheritdoc}
   */
  protected static function entityAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Also reset the 'entity_test' entity access control handler because
    // comment access also depends on access to the commented entity type.
    \Drupal::entityTypeManager()->getAccessControlHandler('entity_test')->resetCache();
    return parent::entityAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function testRelated() {
    $this->markTestSkipped('Remove this in https://www.drupal.org/project/jsonapi/issues/2940339');
  }

  /**
   * {@inheritdoc}
   */
  protected static function getIncludePermissions() {
    return [
      'type' => ['administer comment types'],
      'uid' => ['access user profiles'],
    ];
  }

}
