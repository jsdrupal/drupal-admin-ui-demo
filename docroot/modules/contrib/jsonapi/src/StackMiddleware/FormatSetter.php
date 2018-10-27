<?php

namespace Drupal\jsonapi\StackMiddleware;

use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Sets the 'api_json' format on all requests to JSON API-managed routes.
 *
 * @internal
 */
class FormatSetter implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The JSON API base path.
   *
   * @var string
   */
  protected $jsonApiBasePath;

  /**
   * Constructs a FormatSetter object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param string $jsonapi_base_path
   *   The JSON API base path.
   */
  public function __construct(HttpKernelInterface $http_kernel, $jsonapi_base_path) {
    $this->httpKernel = $http_kernel;
    $this->jsonApiBasePath = $jsonapi_base_path;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    if ($this->isJsonApiRequest($request)) {
      $request->setRequestFormat('api_json');
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

  /**
   * Checks whether the current request is a JSON API request.
   *
   * Inspects:
   * - request parameters
   * - request path (uses a heuristic, because e.g. language negotiation may use
   *   path prefixes)
   * - 'Accept' request header value.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return bool
   *   Whether the current request is a JSON API request.
   */
  protected function isJsonApiRequest(Request $request) {
    $is_jsonapi_route = $request->attributes->get(Routes::JSON_API_ROUTE_FLAG_KEY);
    // Check if the path indicates that the request intended to target a JSON
    // API route (but may not have because of an incorrect parameter or minor
    // typo).
    $jsonapi_route_intended = strpos($request->getPathInfo(), "{$this->jsonApiBasePath}/") !== FALSE;
    // Check if the 'Accept' header includes the JSON API MIME type.
    $request_has_jsonapi_media_type = count(array_filter($request->getAcceptableContentTypes(), function ($accept) {
      return strpos($accept, 'application/vnd.api+json') === 0;
    }));
    return $is_jsonapi_route || ($jsonapi_route_intended && $request_has_jsonapi_media_type);
  }

}
