<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use Drupal\jsonapi\ResourceResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Utility methods for handling resource responses.
 *
 * @internal
 */
trait ResourceResponseTestTrait {

  /**
   * Merges individual responses into a collection response.
   *
   * Here, a collection response refers to a response with multiple resource
   * objects. Not necessarily to a response to a collection route. In both
   * cases, the document should indistinguishable.
   *
   * @param array $responses
   *   An array or ResourceResponses to be merged.
   * @param string|null $self_link
   *   The self link for the merged document if one should be set.
   * @param bool $is_multiple
   *   Whether the responses are for a multiple cardinality field. This cannot
   *   be deduced from the number of responses, because a multiple cardinality
   *   field may have only one value.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The merged ResourceResponse.
   */
  protected static function toCollectionResourceResponse(array $responses, $self_link, $is_multiple) {
    assert(count($responses) > 0);
    $merged_document = [];
    $merged_cacheability = new CacheableMetadata();
    foreach ($responses as $response) {
      $response_document = $response->getResponseData();
      $merge_errors = function ($errors) use (&$merged_document, $is_multiple) {
        foreach ($errors as $error) {
          if ($is_multiple) {
            $merged_document['meta']['errors'][] = $error;
          }
          else {
            $merged_document['errors'][] = $error;
          }
        }
      };
      // If any of the response documents had top-level or meta errors, we
      // should later expect the merged document to have all these errors
      // under the 'meta' member.
      if (!empty($response_document['errors'])) {
        $merge_errors($response_document['errors']);
      }
      if (!empty($response_document['meta']['errors'])) {
        $merge_errors($response_document['meta']['errors']);
      }
      elseif (isset($response_document['data'])) {
        $response_data = $response_document['data'];
        if (!isset($merged_document['data'])) {
          $merged_document['data'] = static::isResourceIdentifier($response_data) && $is_multiple
            ? [$response_data]
            : $response_data;
        }
        else {
          $response_resources = static::isResourceIdentifier($response_data)
            ? [$response_data]
            : $response_data;
          foreach ($response_resources as $response_resource) {
            $merged_document['data'][] = $response_resource;
          }
        }
      }
      $merged_cacheability->addCacheableDependency($response->getCacheableMetadata());
    }
    // Until we can reasonably know what caused an error, we shouldn't include
    // 'self' links in error documents. For example, a 404 shouldn't have a
    // 'self' link because HATEOAS links shouldn't point to resources which do
    // not exist.
    if (isset($merged_document['errors'])) {
      unset($merged_document['links']);
    }
    else {
      $merged_document['links'] = ['self' => $self_link];
      // @todo Assign this to every document, even with errors in https://www.drupal.org/project/jsonapi/issues/2949807
      $merged_document['jsonapi'] = [
        'meta' => [
          'links' => [
            'self' => 'http://jsonapi.org/format/1.0/',
          ],
        ],
        'version' => '1.0',
      ];
    }
    // If any successful code exists, use that one. Partial success isn't
    // defined by HTTP semantics. When different response codes exist, fall
    // back to a more general code. Any one success will make the merged request
    // a success.
    $merged_response_code = array_reduce($responses, function ($merged_response_code, $response) {
      $response_code = $response->getStatusCode();
      assert($response_code >= 200 && $response_code < 500, 'Responses must be valid and complete to be merged.');
      assert(!($response_code >= 300 && $response_code < 400), 'Redirect responses cannot be merged.');
      // In the initial case, use the first response code.
      if (is_null($merged_response_code)) {
        return $response_code;
      }
      // If the codes match, keep them.
      elseif ($merged_response_code === $response_code) {
        return $merged_response_code;
      }
      // If the current code or the prior code is successful, use a general 200.
      elseif (($response_code >= 200 && $response_code < 300) || ($merged_response_code >= 200 && $merged_response_code < 300)) {
        return 200;
      }
      // There are different client errors, return a general 400.
      else {
        return 400;
      }
    }, NULL);
    return (new ResourceResponse($merged_document, $merged_response_code))->addCacheableDependency($merged_cacheability);
  }

  /**
   * Gets an array of expected ResourceResponses for the given include paths.
   *
   * @param array $include_paths
   *   The list of relationship include paths for which to get expected data.
   * @param array $request_options
   *   Request options to apply.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The expected ResourceResponse.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getExpectedIncludedResourceResponse(array $include_paths, array $request_options) {
    $resource_data = array_reduce($include_paths, function ($data, $path) use ($request_options) {
      $field_names = explode('.', $path);
      $entity = $this->entity;
      foreach ($field_names as $field_name) {
        $collected_responses = [];
        $field_access = static::entityFieldAccess($entity, $field_name, 'view', $this->account);
        if (!$field_access->isAllowed()) {
          $collected_responses[] = static::getAccessDeniedResponse($entity, $field_access, $field_name, 'The current user is not allowed to view this relationship.');
          break;
        }
        if ($target_entity = $entity->{$field_name}->entity) {
          $target_access = static::entityAccess($target_entity, 'view', $this->account);
          if (!$target_access->isAllowed()) {
            $resource_identifier = static::toResourceIdentifier($target_entity);
            if (!static::collectionHasResourceIdentifier($resource_identifier, $data['already_checked'])) {
              $data['already_checked'][] = $resource_identifier;
              // @todo remove this in https://www.drupal.org/project/jsonapi/issues/2943176
              $error_id = '/' . $resource_identifier['type'] . '/' . $resource_identifier['id'];
              $collected_responses[] = static::getAccessDeniedResponse($entity, $target_access, NULL, NULL, '/data', $error_id);
            }
            break;
          }
        }
        $psr_responses = $this->getResponses([static::getRelatedLink(static::toResourceIdentifier($entity), $field_name)], $request_options);
        $collected_responses[] = static::toCollectionResourceResponse(static::toResourceResponses($psr_responses), NULL, TRUE);
        $entity = $entity->{$field_name}->entity;
      }
      if (!empty($collected_responses)) {
        $data['responses'][$path] = static::toCollectionResourceResponse($collected_responses, NULL, TRUE);
      }
      return $data;
    }, ['responses' => [], 'already_checked' => []]);
    return static::toCollectionResourceResponse($resource_data['responses'], NULL, TRUE);
  }

  /**
   * Maps an array of PSR responses to JSON API ResourceResponses.
   *
   * @param \Psr\Http\Message\ResponseInterface[] $responses
   *   The PSR responses to be mapped.
   *
   * @return \Drupal\jsonapi\ResourceResponse[]
   *   The ResourceResponses.
   */
  protected static function toResourceResponses(array $responses) {
    return array_map([self::class, 'toResourceResponse'], $responses);
  }

  /**
   * Maps a response object to a JSON API ResourceResponse.
   *
   * This helper can be used to ease comparing, recording and merging
   * cacheable responses and to have easier access to the JSON API document as
   * an array instead of a string.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   A PSR response to be mapped.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The ResourceResponse.
   */
  protected static function toResourceResponse(ResponseInterface $response) {
    $cacheability = new CacheableMetadata();
    if ($cache_tags = $response->getHeader('X-Drupal-Cache-Tags')) {
      $cacheability->addCacheTags(explode(' ', $cache_tags[0]));
    }
    if ($cache_contexts = $response->getHeader('X-Drupal-Cache-Contexts')) {
      $cacheability->addCacheContexts(explode(' ', $cache_contexts[0]));
    }
    if ($dynamic_cache = $response->getHeader('X-Drupal-Dynamic-Cache')) {
      $cacheability->setCacheMaxAge(($dynamic_cache[0] === 'UNCACHEABLE') ? 0 : Cache::PERMANENT);
    }
    $related_document = Json::decode($response->getBody());
    $resource_response = new ResourceResponse($related_document, $response->getStatusCode());
    return $resource_response->addCacheableDependency($cacheability);
  }

  /**
   * Maps an entity to a resource identifier.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to map to a resource identifier.
   *
   * @return array
   *   A resource identifier for the given entity.
   */
  protected static function toResourceIdentifier(EntityInterface $entity) {
    return [
      'type' => $entity->getEntityTypeId() . '--' . $entity->bundle(),
      'id' => $entity->uuid(),
    ];
  }

  /**
   * Checks if a given array is a resource identifier.
   *
   * @param array $data
   *   An array to check.
   *
   * @return bool
   *   TRUE if the array has a type and ID, FALSE otherwise.
   */
  protected static function isResourceIdentifier(array $data) {
    return array_key_exists('type', $data) && array_key_exists('id', $data);
  }

  /**
   * Sorts a collection of resources or resource identifiers.
   *
   * This is useful for asserting collections or resources where order cannot
   * be known in advance.
   *
   * @param array $resources
   *   The resource or resource identifier.
   */
  protected static function sortResourceCollection(array &$resources) {
    usort($resources, function ($a, $b) {
      return strcmp("{$a['type']}:{$a['id']}", "{$b['type']}:{$b['id']}");
    });
  }

  /**
   * Determines if a given resource exists in a list of resources.
   *
   * @param array $needle
   *   The resource or resource identifier.
   * @param array $haystack
   *   The list of resources or resource identifiers to search.
   *
   * @return bool
   *   TRUE if the needle exists is present in the haystack, FALSE otherwise.
   */
  protected static function collectionHasResourceIdentifier(array $needle, array $haystack) {
    foreach ($haystack as $resource) {
      if ($resource['type'] == $needle['type'] && $resource['id'] == $needle['id']) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Turns a list of relationship field names into an array of link paths.
   *
   * @param array $relationship_field_names
   *   The relationships field names for which to build link paths.
   * @param string $type
   *   The type of link to get. Either 'relationship' or 'related'.
   *
   * @return array
   *   An array of link paths, keyed by relationship field name.
   */
  protected static function getLinkPaths(array $relationship_field_names, $type) {
    assert($type === 'relationship' || $type === 'related');
    return array_reduce($relationship_field_names, function ($link_paths, $relationship_field_name) use ($type) {
      $tail = $type === 'relationship' ? 'self' : $type;
      $link_paths[$relationship_field_name] = "data.relationships.$relationship_field_name.links.$tail";
      return $link_paths;
    }, []);
  }

  /**
   * Extracts links from a document using a list of relationship field names.
   *
   * @param array $link_paths
   *   A list of paths to link values keyed by a name.
   * @param array $document
   *   A JSON API document.
   *
   * @return array
   *   The extracted links, keyed by the original associated key name.
   */
  protected static function extractLinks(array $link_paths, array $document) {
    return array_map(function ($link_path) use ($document) {
      $link = array_reduce(
        explode('.', $link_path),
        'array_column',
        [$document]
      );
      return ($link) ? reset($link) : NULL;
    }, $link_paths);
  }

  /**
   * Creates individual resource links for a list of resource identifiers.
   *
   * @param array $resource_identifiers
   *   A list of resource identifiers for which to create links.
   *
   * @return string[]
   *   The resource links.
   */
  protected static function getResourceLinks(array $resource_identifiers) {
    return array_map([static::class, 'getResourceLink'], $resource_identifiers);
  }

  /**
   * Creates an individual resource link for a given resource identifier.
   *
   * @param array $resource_identifier
   *   A resource identifier for which to create a link.
   *
   * @return string
   *   The resource link.
   */
  protected static function getResourceLink(array $resource_identifier) {
    assert(static::isResourceIdentifier($resource_identifier));
    $resource_type = $resource_identifier['type'];
    $resource_id = $resource_identifier['id'];
    $entity_type_id = explode('--', $resource_type)[0];
    $url = Url::fromRoute(sprintf('jsonapi.%s.individual', $resource_type), [$entity_type_id => $resource_id]);
    return $url->setAbsolute()->toString();
  }

  /**
   * Creates a relationship link for a given resource identifier and field.
   *
   * @param array $resource_identifier
   *   A resource identifier for which to create a link.
   * @param string $relationship_field_name
   *   The relationship field for which to create a link.
   *
   * @return string
   *   The relationship link.
   */
  protected static function getRelationshipLink(array $resource_identifier, $relationship_field_name) {
    return static::getResourceLink($resource_identifier) . "/relationships/$relationship_field_name";
  }

  /**
   * Creates a related resource link for a given resource identifier and field.
   *
   * @param array $resource_identifier
   *   A resource identifier for which to create a link.
   * @param string $relationship_field_name
   *   The relationship field for which to create a link.
   *
   * @return string
   *   The related resource link.
   */
  protected static function getRelatedLink(array $resource_identifier, $relationship_field_name) {
    return static::getResourceLink($resource_identifier) . "/$relationship_field_name";
  }

  /**
   * Gets an array of related responses for the given field names.
   *
   * @param array $relationship_field_names
   *   The list of relationship field names for which to get responses.
   * @param array $request_options
   *   Request options to apply.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   (optional) The entity for which to get expected related responses.
   *
   * @return array
   *   The related responses, keyed by relationship field names.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getRelatedResponses(array $relationship_field_names, array $request_options, EntityInterface $entity = NULL) {
    $entity = $entity ?: $this->entity;
    $links = array_map(function ($relationship_field_name) use ($entity) {
      return static::getRelatedLink(static::toResourceIdentifier($entity), $relationship_field_name);
    }, array_combine($relationship_field_names, $relationship_field_names));
    return $this->getResponses($links, $request_options);
  }

  /**
   * Gets an array of relationship responses for the given field names.
   *
   * @param array $relationship_field_names
   *   The list of relationship field names for which to get responses.
   * @param array $request_options
   *   Request options to apply.
   *
   * @return array
   *   The relationship responses, keyed by relationship field names.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getRelationshipResponses(array $relationship_field_names, array $request_options) {
    $links = array_map(function ($relationship_field_name) {
      return static::getRelationshipLink(static::toResourceIdentifier($this->entity), $relationship_field_name);
    }, array_combine($relationship_field_names, $relationship_field_names));
    return $this->getResponses($links, $request_options);
  }

  /**
   * Gets responses from an array of links.
   *
   * @param array $links
   *   A keyed array of links.
   * @param array $request_options
   *   Request options to apply.
   *
   * @return array
   *   The fetched array of responses, keys are preserved.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getResponses(array $links, array $request_options) {
    return array_reduce(array_keys($links), function ($related_responses, $key) use ($links, $request_options) {
      $related_responses[$key] = $this->request('GET', Url::fromUri($links[$key]), $request_options);
      return $related_responses;
    }, []);
  }

  /**
   * Gets a generic forbidden response.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to generate the forbidden response.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The denied AccessResult. This can carry a reason and cacheability data.
   * @param string|null $relationship_field_name
   *   (optional) The field name to which the forbidden result applies. Useful
   *   for testing related/relationship routes and includes.
   * @param string|null $detail
   *   (optional) Details for the JSON API error object.
   * @param string|null $pointer
   *   (optional) Document pointer for the JSON API error object.
   * @param string|null $id
   *   (optional) ID for the JSON API error object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The forbidden ResourceResponse.
   */
  protected static function getAccessDeniedResponse(EntityInterface $entity, AccessResultInterface $access, $relationship_field_name = NULL, $detail = NULL, $pointer = NULL, $id = NULL) {
    $detail = ($detail) ? $detail : 'The current user is not allowed to GET the selected resource.';
    if ($access instanceof AccessResultReasonInterface && ($reason = $access->getReason())) {
      $detail .= ' ' . $reason;
    }
    $resource_identifier = static::toResourceIdentifier($entity);
    $error = [
      'status' => 403,
      'title' => 'Forbidden',
      'detail' => $detail,
      'links' => [
        'info' => HttpExceptionNormalizer::getInfoUrl(403),
      ],
      'code' => 0,
      // @todo uncomment in https://www.drupal.org/project/jsonapi/issues/2943176
      /* 'id' => '/' . $resource_identifier['type'] . '/' . $resource_identifier['id'], */
    ];
    if (!is_null($id)) {
      $error['id'] = $id;
    }
    if ($relationship_field_name || $pointer) {
      $error['source']['pointer'] = ($pointer) ? $pointer : $relationship_field_name;
    }
    return (new ResourceResponse(['errors' => [$error]], 403))->addCacheableDependency($access);
  }

}
