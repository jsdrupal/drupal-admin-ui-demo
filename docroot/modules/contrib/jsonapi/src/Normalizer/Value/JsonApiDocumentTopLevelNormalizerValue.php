<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\jsonapi\JsonApiSpec;

/**
 * Helps normalize the top level document in compliance with the JSON API spec.
 *
 * @internal
 */
class JsonApiDocumentTopLevelNormalizerValue implements ValueExtractorInterface, RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * The values.
   *
   * @var array
   */
  protected $values;

  /**
   * The includes.
   *
   * @var array
   */
  protected $includes;

  /**
   * The resource path.
   *
   * @var array
   */
  protected $context;

  /**
   * Is collection?
   *
   * @var bool
   */
  protected $isCollection;

  /**
   * The link manager.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * The link context.
   *
   * @var array
   */
  protected $linkContext;

  /**
   * Instantiates a JsonApiDocumentTopLevelNormalizerValue object.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $values
   *   The data to normalize. It can be either a straight up entity or a
   *   collection of entities.
   * @param array $context
   *   The context.
   * @param array $link_context
   *   All the objects and variables needed to generate the links for this
   *   relationship.
   * @param bool $is_collection
   *   TRUE if this is a serialization for a list.
   */
  public function __construct(array $values, array $context, array $link_context, $is_collection = FALSE) {
    $this->values = $values;
    array_walk($values, [$this, 'addCacheableDependency']);
    // Make sure that different sparse fieldsets are cached differently.
    $this->addCacheContexts(array_map(function ($query_parameter_name) {
      return sprintf('url.query_args:%s', $query_parameter_name);
    }, JsonApiSpec::getReservedQueryParameters()));
    // Every JSON API document contains absolute URLs.
    $this->addCacheContexts(['url.site']);

    $this->context = $context;
    $this->isCollection = $is_collection;
    $this->linkManager = $link_context['link_manager'];
    // Remove the manager and store the link context.
    unset($link_context['link_manager']);
    $this->linkContext = $link_context;
    // Get an array of arrays of includes.
    $this->includes = array_map(function ($value) {
      return $value->getIncludes();
    }, $values);
    // Flatten the includes.
    $this->includes = array_reduce($this->includes, function ($carry, $includes) {
      array_walk($includes, [$this, 'addCacheableDependency']);
      return array_merge($carry, $includes);
    }, []);
    // Filter the empty values.
    $this->includes = array_filter($this->includes);
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    // Create the array of normalized fields, starting with the URI.
    $rasterized = [
      'data' => [],
      'jsonapi' => [
        'version' => JsonApiSpec::SUPPORTED_SPECIFICATION_VERSION,
        'meta' => [
          'links' => ['self' => JsonApiSpec::SUPPORTED_SPECIFICATION_PERMALINK],
        ],
      ],
      'links' => [],
    ];

    foreach ($this->values as $normalizer_value) {
      if ($normalizer_value instanceof HttpExceptionNormalizerValue) {
        $previous_errors = NestedArray::getValue($rasterized, ['meta', 'errors']) ?: [];
        // Add the errors to the pre-existing errors.
        $rasterized['meta']['errors'] = array_merge($previous_errors, $normalizer_value->rasterizeValue());
      }
      else {
        $rasterized_value = $normalizer_value->rasterizeValue();
        if (array_key_exists('data', $rasterized_value) && array_key_exists('links', $rasterized_value)) {
          $rasterized['data'][] = $rasterized_value['data'];
          $rasterized['links'] = NestedArray::mergeDeep($rasterized['links'], $rasterized_value['links']);
        }
        else {
          $rasterized['data'][] = $rasterized_value;
        }
      }
    }
    // Deal with the single entity case.
    $rasterized['data'] = $this->isCollection ?
      array_filter($rasterized['data']) :
      reset($rasterized['data']);

    // Add the self link.
    if ($this->context['request']) {
      /* @var \Symfony\Component\HttpFoundation\Request $request */
      $request = $this->context['request'];
      $rasterized['links'] += [
        'self' => $this->linkManager->getRequestLink($request),
      ];
      // If this is a collection we need to append the pager data.
      if ($this->isCollection) {
        // Add the pager links.
        $rasterized['links'] += $this->linkManager->getPagerLinks($request, $this->linkContext);

        // Add the pre-calculated total count to the meta section.
        if (isset($this->context['total_count'])) {
          $rasterized = NestedArray::mergeDeepArray([
            $rasterized,
            ['meta' => ['count' => $this->context['total_count']]],
          ]);
        }
      }
    }

    // This is the top-level JSON API document, therefore the rasterized value
    // must include the rasterized includes: there is no further level to bubble
    // them to!
    $included = array_filter($this->rasterizeIncludes());
    if (!empty($included)) {
      foreach ($included as $included_item) {
        if ($included_item['data'] === FALSE) {
          unset($included_item['data']);
          $rasterized = NestedArray::mergeDeep($rasterized, $included_item);
        }
        else {
          $rasterized['included'][] = $included_item['data'];
        }
      }
    }

    if (empty($rasterized['links'])) {
      unset($rasterized['links']);
    }

    return $rasterized;
  }

  /**
   * Gets a flattened list of includes in all the chain.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue[]
   *   The array of included relationships.
   */
  public function getIncludes() {
    $nested_includes = array_map(function ($include) {
      return $include->getIncludes();
    }, $this->includes);
    $includes = array_reduce(array_filter($nested_includes), function ($carry, $item) {
      return array_merge($carry, $item);
    }, $this->includes);
    // Make sure we don't output duplicate includes.
    return array_values(array_reduce($includes, function ($unique_includes, $include) {
      $rasterized_include = $include->rasterizeValue();

      if ($rasterized_include['data'] === FALSE) {
        $unique_includes[] = $include;
      }
      else {
        $unique_key = $rasterized_include['data']['type'] . ':' . $rasterized_include['data']['id'];
        $unique_includes[$unique_key] = $include;
      }
      return $unique_includes;
    }, []));
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeIncludes() {
    // First gather all the includes in the chain.
    return array_map(function ($include) {
      return $include->rasterizeValue();
    }, $this->getIncludes());
  }

}
