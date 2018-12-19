<?php

namespace Drupal\jsonapi\Routing;

use Drupal\Core\Routing\Enhancer\RouteEnhancerInterface;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\Query\Filter;
use Drupal\jsonapi\Query\Sort;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Processes the request query parameters.
 *
 * @internal
 */
class JsonApiParamEnhancer implements RouteEnhancerInterface {

  /**
   * The filter normalizer.
   *
   * @var \Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $filterNormalizer;

  /**
   * The sort normalizer.
   *
   * @var \Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $sortNormalizer;

  /**
   * The page normalizer.
   *
   * @var Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $pageNormalizer;

  /**
   * {@inheritdoc}
   */
  public function __construct(DenormalizerInterface $filter_normalizer, DenormalizerInterface $sort_normalizer, DenormalizerInterface $page_normalizer) {
    $this->filterNormalizer = $filter_normalizer;
    $this->sortNormalizer = $sort_normalizer;
    $this->pageNormalizer = $page_normalizer;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route) {
    return (bool) Routes::getResourceTypeNameFromParameters($route->getDefaults());
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    $options = [];

    $resource_type = Routes::getResourceTypeNameFromParameters($defaults);
    $context = [
      'entity_type_id' => $resource_type->getEntityTypeId(),
      'bundle' => $resource_type->getBundle(),
    ];

    if ($request->query->has('filter')) {
      $filter = $request->query->get('filter');
      $options['filter'] = $this->filterNormalizer->denormalize($filter, Filter::class, NULL, $context);
    }

    if ($request->query->has('sort')) {
      $sort = $request->query->get('sort');
      $options['sort'] = $this->sortNormalizer->denormalize($sort, Sort::class, NULL, $context);
    }

    $page = ($request->query->has('page')) ? $request->query->get('page') : [];
    $options['page'] = $this->pageNormalizer->denormalize($page, OffsetPage::class);

    $defaults['_json_api_params'] = $options;

    return $defaults;
  }

}
