<?php

namespace Drupal\Tests\jsonapi\Unit\Access;

use Drupal\jsonapi\Access\CustomQueryParameterNamesAccessCheck;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\jsonapi\Access\CustomQueryParameterNamesAccessCheck
 * @group jsonapi
 *
 * @internal
 */
class CustomQueryParameterNamesAccessCheckTest extends UnitTestCase {

  /**
   * Ensures that query params are properly validated.
   *
   * @dataProvider providerTestAccess
   * @covers ::access
   * @covers ::validate
   */
  public function testAccess($name, $valid) {
    $access_checker = new CustomQueryParameterNamesAccessCheck();

    $request = new Request();
    $request->attributes->set('_json_api_params', [$name => '123']);
    $result = $access_checker->access($request);

    if ($valid) {
      $this->assertTrue($result->isAllowed());
    }
    else {
      $this->assertFalse($result->isAllowed());
    }
  }

  /**
   * Data provider for testAccess.
   */
  public function providerTestAccess() {
    $data = [];

    $data['Official query parameter: sort'] = ['sort', TRUE];
    $data['Official query parameter: page'] = ['page', TRUE];
    $data['Official query parameter: filter'] = ['filter', TRUE];

    $data['Valid member, but invalid custom query parameter'] = ['foobar', FALSE];

    $data['Valid custom query parameter: dash'] = ['foo-bar', TRUE];
    $data['Valid custom query parameter: underscore'] = ['foo_bar', TRUE];
    $data['Valid custom query parameter: camelcase'] = ['fooBar', TRUE];

    return $data;
  }

}
