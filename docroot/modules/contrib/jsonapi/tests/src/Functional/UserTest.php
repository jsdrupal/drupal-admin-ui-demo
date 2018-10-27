<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use Drupal\Tests\rest\Functional\BcTimestampNormalizerUnixTestTrait;
use Drupal\user\Entity\User;
use GuzzleHttp\RequestOptions;

/**
 * JSON API integration test for the "User" content entity type.
 *
 * @group jsonapi
 */
class UserTest extends ResourceTestBase {

  use BcTimestampNormalizerUnixTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['user'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'user';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'user--user';

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
  protected static $labelFieldName = 'name';

  /**
   * {@inheritdoc}
   */
  protected static $firstCreatedEntityId = 4;

  /**
   * {@inheritdoc}
   */
  protected static $secondCreatedEntityId = 5;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    // @todo Remove this in
    $this->grantPermissionsToTestedRole(['access content']);

    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['access user profiles']);
        break;

      case 'POST':
      case 'PATCH':
      case 'DELETE':
        $this->grantPermissionsToTestedRole(['administer users']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "Llama" user.
    $user = User::create(['created' => 123456789]);
    $user->setUsername('Llama')
      ->setChangedTime(123456789)
      ->activate()
      ->save();

    return $user;
  }

  /**
   * {@inheritdoc}
   */
  protected function createAnotherEntity($key) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getEntityDuplicate($this->entity, $key);
    $user->setUsername($user->label() . '_' . $key);
    $user->setEmail("$key@example.com");
    $user->save();
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/user/user/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    return [
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
        'type' => 'user--user',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'created' => 123456789,
          // @todo uncomment this in https://www.drupal.org/project/jsonapi/issues/2929932
          // 'created' => $this->formatExpectedTimestampItemValues(123456789),
          'changed' => $this->entity->getChangedTime(),
          // @todo uncomment this in https://www.drupal.org/project/jsonapi/issues/2929932
          // 'changed' => $this->formatExpectedTimestampItemValues($this->entity->getChangedTime()),
          'default_langcode' => TRUE,
          'langcode' => 'en',
          'name' => 'Llama',
          'uid' => 3,
          'uuid' => $this->entity->uuid(),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    return [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'name' => 'Dramallama',
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
        return "The 'access user profiles' permission is required and the user must be active.";

      case 'PATCH':
      case 'DELETE':
        return '';

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * Tests PATCHing security-sensitive base fields of the logged in account.
   */
  public function testPatchDxForSecuritySensitiveBaseFields() {
    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/jsonapi/issues/2878463.
    $url = Url::fromRoute(sprintf('jsonapi.user--user.individual'), ['user' => $this->account->uuid()]);
    /* $url = $this->account->toUrl('jsonapi'); */

    $original_normalization = $this->normalize($this->account, $url);
    // @todo Remove the array_diff_key() call in https://www.drupal.org/node/2821077.
    $original_normalization['data']['attributes'] = array_diff_key(
      $original_normalization['data']['attributes'],
      ['created' => TRUE, 'changed' => TRUE, 'name' => TRUE]
    );

    // Since this test must be performed by the user that is being modified,
    // we must use $this->account, not $this->entity.
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    // Test case 1: changing email.
    $normalization = $original_normalization;
    $normalization['data']['attributes']['mail'] = 'new-email@example.com';
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // DX: 422 when changing email without providing the password.
    $response = $this->request('PATCH', $url, $request_options);
    // @todo Remove $expected + assertResourceResponse() in favor of the commented line below once https://www.drupal.org/project/jsonapi/issues/2943176 lands.
    $expected_document = [
      'errors' => [
        [
          'title' => 'Unprocessable Entity',
          'status' => 422,
          'detail' => 'mail: Your current password is missing or incorrect; it\'s required to change the Email.',
          'code' => 0,
          'source' => [
            'pointer' => '/data/attributes/mail',
          ],
        ],
      ],
    ];
    $this->assertResourceResponse(422, $expected_document, $response);
    /* $this->assertResourceErrorResponse(422, 'Unprocessable Entity', 'mail: Your current password is missing or incorrect; it\'s required to change the Email.', $response, '/data/attributes/mail'); */

    $normalization['data']['attributes']['pass']['existing'] = 'wrong';
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // DX: 422 when changing email while providing a wrong password.
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceResponse(422, $expected_document, $response);

    $normalization['data']['attributes']['pass']['existing'] = $this->account->passRaw;
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // 200 for well-formed request.
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);

    // Test case 2: changing password.
    $normalization = $original_normalization;
    $normalization['data']['attributes']['mail'] = 'new-email@example.com';
    $new_password = $this->randomString();
    $normalization['data']['attributes']['pass']['value'] = $new_password;
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // DX: 422 when changing password without providing the current password.
    $response = $this->request('PATCH', $url, $request_options);
    // @todo Remove $expected + assertResourceResponse() in favor of the commented line below once https://www.drupal.org/project/jsonapi/issues/2943176 lands.
    $expected_document = [
      'errors' => [
        [
          'title' => 'Unprocessable Entity',
          'status' => 422,
          'detail' => 'pass: Your current password is missing or incorrect; it\'s required to change the Password.',
          'code' => 0,
          'source' => [
            'pointer' => '/data/attributes/pass',
          ],
        ],
      ],
    ];
    $this->assertResourceResponse(422, $expected_document, $response);
    /* $this->assertResourceErrorResponse(422, 'Unprocessable Entity', 'pass: Your current password is missing or incorrect; it\'s required to change the Password.', $response, '/data/attributes/pass'); */

    $normalization['data']['attributes']['pass']['existing'] = $this->account->passRaw;
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // 200 for well-formed request.
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);

    // Verify that we can log in with the new password.
    $this->assertRpcLogin($this->account->getAccountName(), $new_password);

    // Update password in $this->account, prepare for future requests.
    $this->account->passRaw = $new_password;
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    // Test case 3: changing name.
    $normalization = $original_normalization;
    $normalization['data']['attributes']['mail'] = 'new-email@example.com';
    $normalization['data']['attributes']['pass']['existing'] = $new_password;
    $normalization['data']['attributes']['name'] = 'Cooler Llama';
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // DX: 403 when modifying username without required permission.
    $response = $this->request('PATCH', $url, $request_options);
    // @todo Remove $expected + assertResourceResponse() in favor of the commented line below once https://www.drupal.org/project/jsonapi/issues/2943176 lands.
    $expected_document = [
      'errors' => [
        [
          'title' => 'Forbidden',
          'status' => 403,
          'detail' => 'The current user is not allowed to PATCH the selected field (name).',
          'links' => [
            'info' => HttpExceptionNormalizer::getInfoUrl(403),
          ],
          'code' => 0,
          'id' => '/user--user/' . $this->account->uuid(),
          'source' => [
            'pointer' => '/data/attributes/name',
          ],
        ],
      ],
    ];
    $this->assertResourceResponse(403, $expected_document, $response);
    /* $this->assertResourceErrorResponse(403, 'Forbidden', 'The current user is not allowed to PATCH the selected field (name).', $response, '/data/attributes/name'); */

    $this->grantPermissionsToTestedRole(['change own username']);

    // 200 for well-formed request.
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);

    // Verify that we can log in with the new username.
    $this->assertRpcLogin('Cooler Llama', $new_password);
  }

  /**
   * Verifies that logging in with the given username and password works.
   *
   * @param string $username
   *   The username to log in with.
   * @param string $password
   *   The password to log in with.
   */
  protected function assertRpcLogin($username, $password) {
    $request_body = [
      'name' => $username,
      'pass' => $password,
    ];
    $request_options = [
      RequestOptions::HEADERS => [],
      RequestOptions::BODY => Json::encode($request_body),
    ];
    $response = $this->request('POST', Url::fromRoute('user.login.http')->setRouteParameter('_format', 'json'), $request_options);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests PATCHing security-sensitive base fields to change other users.
   */
  public function testPatchSecurityOtherUser() {
    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/jsonapi/issues/2878463.
    $url = Url::fromRoute(sprintf('jsonapi.user--user.individual'), ['user' => $this->account->uuid()]);
    /* $url = $this->account->toUrl('jsonapi'); */

    $original_normalization = $this->normalize($this->account, $url);

    // Since this test must be performed by the user that is being modified,
    // we must use $this->account, not $this->entity.
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $normalization = $original_normalization;
    $normalization['data']['attributes']['mail'] = 'new-email@example.com';
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // Try changing user 1's email.
    $user1 = $original_normalization;
    $user1['data']['attributes']['mail'] = 'another_email_address@example.com';
    $user1['data']['attributes']['uid'] = 1;
    $user1['data']['attributes']['name'] = 'another_user_name';
    $user1['data']['attributes']['pass']['existing'] = $this->account->passRaw;
    $request_options[RequestOptions::BODY] = Json::encode($user1);
    $response = $this->request('PATCH', $url, $request_options);
    // Ensure the email address has not changed.
    $this->assertEquals('admin@example.com', $this->entityStorage->loadUnchanged(1)->getEmail());
    $expected_document = [
      'errors' => [
        [
          'title' => 'Forbidden',
          'status' => 403,
          'detail' => 'The current user is not allowed to PATCH the selected field (uid). The entity ID cannot be changed',
          'links' => [
            'info' => HttpExceptionNormalizer::getInfoUrl(403),
          ],
          'code' => 0,
          'id' => '/user--user/' . $this->account->uuid(),
          'source' => [
            'pointer' => '/data/attributes/uid',
          ],
        ],
      ],
    ];
    // @todo Uncomment this assertion in https://www.drupal.org/project/jsonapi/issues/2939810.
    // $this->assertResourceResponse(403, $expected_document, $response);
    // @todo Remove $expected + assertResourceResponse() in favor of the commented line below once https://www.drupal.org/project/jsonapi/issues/2943176 lands.
    /* $this->assertResourceErrorResponse(403, 'Forbidden', 'The current user is not allowed to PATCH the selected field (uid). The entity ID cannot be changed', $response, '/data/attributes/uid'); */
  }

  /**
   * Tests GETting privacy-sensitive base fields.
   */
  public function testGetMailFieldOnlyVisibleToOwner() {
    // Create user B, with the same roles (and hence permissions) as user A.
    $user_a = $this->account;
    $pass = user_password();
    $user_b = User::create([
      'name' => 'sibling-of-' . $user_a->getAccountName(),
      'mail' => 'sibling-of-' . $user_a->getAccountName() . '@example.com',
      'pass' => $pass,
      'status' => 1,
      'roles' => $user_a->getRoles(),
    ]);
    $user_b->save();
    $user_b->passRaw = $pass;

    // Grant permission to role that both users use.
    $this->grantPermissionsToTestedRole(['access user profiles']);

    $collection_url = Url::fromRoute('jsonapi.user--user.collection');
    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/jsonapi/issues/2878463.
    $user_a_url = Url::fromRoute(sprintf('jsonapi.user--user.individual'), ['user' => $user_a->uuid()]);
    /* $user_a_url = $user_a->toUrl('jsonapi'); */
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    // Viewing user A as user A: "mail" field is accessible.
    $response = $this->request('GET', $user_a_url, $request_options);
    $doc = Json::decode((string) $response->getBody());
    $this->assertArrayHasKey('mail', $doc['data']['attributes']);
    // Also when looking at the collection.
    $response = $this->request('GET', $collection_url, $request_options);
    $doc = Json::decode((string) $response->getBody());
    $this->assertArrayHasKey('mail', $doc['data'][1]['attributes']);
    $this->assertArrayNotHasKey('mail', $doc['data'][count($doc['data']) - 1]['attributes']);

    // Now request the same URLs, but as user B (same roles/permissions).
    $this->account = $user_b;
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());
    // Viewing user A as user B: "mail" field should be inaccessible.
    $response = $this->request('GET', $user_a_url, $request_options);
    $doc = Json::decode((string) $response->getBody());
    $this->assertArrayNotHasKey('mail', $doc['data']['attributes']);
    // Also when looking at the collection.
    $response = $this->request('GET', $collection_url, $request_options);
    $doc = Json::decode((string) $response->getBody());
    $this->assertArrayNotHasKey('mail', $doc['data'][1]['attributes']);
    $this->assertArrayHasKey('mail', $doc['data'][count($doc['data']) - 1]['attributes']);
  }

  /**
   * Test good error DX when trying to filter users by role.
   */
  public function testQueryInvolvingRoles() {
    $this->setUpAuthorization('GET');

    $collection_url = Url::fromRoute('jsonapi.user--user.collection', [], ['query' => ['filter[roles.uuid][value]' => 'e9b1de3f-9517-4c27-bef0-0301229de792']]);
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $response = $this->request('GET', $collection_url, $request_options);
    $this->assertResourceErrorResponse(400, "Filtering on config entities is not supported by Drupal's entity API. You tried to filter on a Role config entity.", $response);
  }

}
