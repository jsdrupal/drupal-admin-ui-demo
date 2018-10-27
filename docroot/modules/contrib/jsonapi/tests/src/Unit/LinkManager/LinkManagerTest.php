<?php

namespace Drupal\Tests\jsonapi\Unit\LinkManager;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Cmf\Component\Routing\ChainRouterInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @coversDefaultClass \Drupal\jsonapi\LinkManager\LinkManager
 * @group jsonapi
 *
 * @internal
 */
class LinkManagerTest extends UnitTestCase {

  /**
   * The SUT.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $router = $this->prophesize(ChainRouterInterface::class);
    $url_generator = $this->prophesize(UrlGeneratorInterface::class);
    $url_generator->generateFromRoute(Argument::cetera())->willReturnArgument(2);
    $this->linkManager = new LinkManager($router->reveal(), $url_generator->reveal());
  }

  /**
   * @covers ::getPagerLinks
   * @dataProvider getPagerLinksProvider
   */
  public function testGetPagerLinks($offset, $size, $has_next_page, $total, $include_count, array $pages) {
    $assembler = $this->prophesize(UnroutedUrlAssemblerInterface::class);
    $assembler->assemble(Argument::type('string'), Argument::type('array'), FALSE)
      ->will(function ($args) {
        return $args[0] . '?' . UrlHelper::buildQuery($args[1]['query']);
      });

    $container = new ContainerBuilder();
    $container->set('unrouted_url_assembler', $assembler->reveal());
    \Drupal::setContainer($container);

    // Add the extra stuff to the expected query.
    $pages = array_filter($pages);
    $pages = array_map(function ($page) {
      return 'https://example.com/drupal/jsonapi/node/article/07c870e9-491b-4173-8e2b-4e059400af72?amet=pax&page%5Boffset%5D=' . $page['offset'] . '&page%5Blimit%5D=' . $page['limit'];
    }, $pages);

    $request = $this->prophesize(Request::class);
    // Have the request return the desired page parameter.
    $page_param = $this->prophesize(OffsetPage::class);
    $page_param->getOffset()->willReturn($offset);
    $page_param->getSize()->willReturn($size);
    $request->getUri()->willReturn('https://example.com/drupal/jsonapi/node/article/07c870e9-491b-4173-8e2b-4e059400af72?amet=pax');
    $request->getBaseUrl()->willReturn('/drupal');
    $request->getPathInfo()->willReturn('');
    $request->getSchemeAndHttpHost()->willReturn('https://example.com');
    $request->getBaseUrl()->willReturn('/drupal');
    $request->getPathInfo()->willReturn('/jsonapi/node/article/07c870e9-491b-4173-8e2b-4e059400af72');
    $request->get('_json_api_params')->willReturn(['page' => $page_param->reveal()]);
    $request->query = new ParameterBag(['amet' => 'pax']);

    $context = ['has_next_page' => $has_next_page];
    if ($include_count) {
      $context['total_count'] = $total;
    }

    $links = $this->linkManager
      ->getPagerLinks($request->reveal(), $context);
    ksort($pages);
    ksort($links);
    $this->assertSame($pages, $links);
  }

  /**
   * Data provider for testGetPagerLinks.
   *
   * @return array
   *   The data for the test method.
   */
  public function getPagerLinksProvider() {
    return [
      [1, 4, TRUE, 8, TRUE, [
        'first' => ['offset' => 0, 'limit' => 4],
        'prev' => ['offset' => 0, 'limit' => 4],
        'next' => ['offset' => 5, 'limit' => 4],
        'last' => ['offset' => 4, 'limit' => 4],
      ],
      ],
      [6, 4, FALSE, 4, TRUE, [
        'first' => ['offset' => 0, 'limit' => 4],
        'prev' => ['offset' => 2, 'limit' => 4],
        'next' => NULL,
      ],
      ],
      [7, 4, FALSE, 5, FALSE, [
        'first' => ['offset' => 0, 'limit' => 4],
        'prev' => ['offset' => 3, 'limit' => 4],
        'next' => NULL,
      ],
      ],
      [10, 4, FALSE, 20, FALSE, [
        'first' => ['offset' => 0, 'limit' => 4],
        'prev' => ['offset' => 6, 'limit' => 4],
        'next' => NULL,
      ],
      ],
      [5, 4, TRUE, 30, FALSE, [
        'first' => ['offset' => 0, 'limit' => 4],
        'prev' => ['offset' => 1, 'limit' => 4],
        'next' => ['offset' => 9, 'limit' => 4],
      ],
      ],
      [0, 4, TRUE, 100, TRUE, [
        'first' => NULL,
        'prev' => NULL,
        'next' => ['offset' => 4, 'limit' => 4],
        'last' => ['offset' => 96, 'limit' => 4],
      ],
      ],
      [0, 1, FALSE, 1, FALSE, [
        'first' => NULL,
        'prev' => NULL,
        'next' => NULL,
      ],
      ],
      [0, 1, FALSE, 2, FALSE, [
        'first' => NULL,
        'prev' => NULL,
        'next' => NULL,
      ],
      ],
    ];
  }

  /**
   * Test errors.
   *
   * @covers ::getPagerLinks
   * @dataProvider getPagerLinksErrorProvider
   */
  public function testGetPagerLinksError($offset, $size, $has_next_page, $total, $include_count, array $pages) {
    $this->setExpectedException(BadRequestHttpException::class);
    $this->testGetPagerLinks($offset, $size, $has_next_page, $total, $include_count, $pages);
  }

  /**
   * Data provider for testGetPagerLinksError.
   *
   * @return array
   *   The data for the test method.
   */
  public function getPagerLinksErrorProvider() {
    return [
      [0, -5, FALSE, 10, TRUE, [
        'first' => NULL,
        'prev' => NULL,
        'last' => NULL,
        'next' => NULL,
      ],
      ],
    ];
  }

  /**
   * @covers ::getRequestLink
   */
  public function testGetRequestLink() {
    $assembler = $this->prophesize(UnroutedUrlAssemblerInterface::class);
    $assembler->assemble(Argument::type('string'), ['external' => TRUE, 'query' => ['dolor' => 'sid']], FALSE)
      ->will(function ($args) {
          return $args[0] . '?dolor=sid';
      })
      ->shouldBeCalled();

    $container = new ContainerBuilder();
    $container->set('unrouted_url_assembler', $assembler->reveal());
    \Drupal::setContainer($container);

    $request = $this->prophesize(Request::class);
    $request->getUri()->willReturn('https://example.com/drupal/jsonapi/node/article/07c870e9-491b-4173-8e2b-4e059400af72?amet=pax');
    $request->getBaseUrl()->willReturn('/drupal');
    $request->getPathInfo()->willReturn('');
    $request->getSchemeAndHttpHost()->willReturn('https://example.com');
    $request->getBaseUrl()->willReturn('/drupal');
    $request->getPathInfo()->willReturn('/jsonapi/node/article/07c870e9-491b-4173-8e2b-4e059400af72');

    $this->assertSame('https://example.com/drupal/jsonapi/node/article/07c870e9-491b-4173-8e2b-4e059400af72?dolor=sid', $this->linkManager->getRequestLink($request->reveal(), ['dolor' => 'sid']));

    // Get the default query from the request object.
    $this->assertSame('https://example.com/drupal/jsonapi/node/article/07c870e9-491b-4173-8e2b-4e059400af72?amet=pax', $this->linkManager->getRequestLink($request->reveal()));
  }

}
