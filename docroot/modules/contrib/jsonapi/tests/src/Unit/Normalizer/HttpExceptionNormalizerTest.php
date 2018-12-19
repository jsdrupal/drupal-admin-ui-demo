<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer;

use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\HttpExceptionNormalizer
 * @group jsonapi
 *
 * @internal
 */
class HttpExceptionNormalizerTest extends UnitTestCase {

  /**
   * @covers ::normalize
   */
  public function testNormalize() {
    $exception = new AccessDeniedHttpException('lorem', NULL, 13);
    $current_user = $this->prophesize(AccountInterface::class);
    $current_user->hasPermission('access site reports')->willReturn(TRUE);
    $normalizer = new HttpExceptionNormalizer($current_user->reveal());
    $normalized = $normalizer->normalize($exception, 'api_json');
    $normalized = $normalized->rasterizeValue();
    $error = $normalized[0];
    $this->assertNotEmpty($error['meta']);
    $this->assertNotEmpty($error['source']);
    $this->assertEquals(13, $error['code']);
    $this->assertEquals(403, $error['status']);
    $this->assertEquals('Forbidden', $error['title']);
    $this->assertEquals('lorem', $error['detail']);
    $this->assertNull($error['meta']['trace'][1]['args'][0]);

    $current_user = $this->prophesize(AccountInterface::class);
    $current_user->hasPermission('access site reports')->willReturn(FALSE);
    $normalizer = new HttpExceptionNormalizer($current_user->reveal());
    $normalized = $normalizer->normalize($exception, 'api_json');
    $normalized = $normalized->rasterizeValue();
    $error = $normalized[0];
    $this->assertTrue(empty($error['meta']));
    $this->assertTrue(empty($error['source']));
  }

}
