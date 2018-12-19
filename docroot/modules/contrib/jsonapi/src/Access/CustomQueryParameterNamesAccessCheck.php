<?php

namespace Drupal\jsonapi\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\jsonapi\JsonApiSpec;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates custom (implementation-specific) query parameter names.
 *
 * @see http://jsonapi.org/format/#query-parameters
 *
 * @internal
 */
class CustomQueryParameterNamesAccessCheck implements AccessInterface {

  /**
   * Denies access when using invalid custom JSON API query parameter names.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(Request $request) {
    $json_api_params = $request->attributes->get('_json_api_params', []);
    if (!$this->validate($json_api_params)) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

  /**
   * Validates custom JSON API query parameters.
   *
   * @param string[] $json_api_params
   *   The JSON API parameters.
   *
   * @return bool
   *   Whether the parameter is valid.
   */
  protected function validate(array $json_api_params) {
    foreach (array_keys($json_api_params) as $query_parameter_name) {
      // Ignore reserved (official) query parameters.
      if (in_array($query_parameter_name, JsonApiSpec::getReservedQueryParameters())) {
        continue;
      }

      if (!JsonApiSpec::isValidCustomQueryParameter($query_parameter_name)) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
