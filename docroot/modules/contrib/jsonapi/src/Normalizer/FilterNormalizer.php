<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\jsonapi\Context\FieldResolver;
use Drupal\jsonapi\Query\EntityCondition;
use Drupal\jsonapi\Query\Filter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * The normalizer used for JSON API filters.
 *
 * @internal
 */
class FilterNormalizer implements DenormalizerInterface {

  /**
   * The key for the implicit root group.
   */
  const ROOT_ID = '@root';

  /**
   * Key in the filter[<key>] parameter for conditions.
   *
   * @var string
   */
  const CONDITION_KEY = 'condition';

  /**
   * Key in the filter[<key>] parameter for groups.
   *
   * @var string
   */
  const GROUP_KEY = 'group';

  /**
   * Key in the filter[<id>][<key>] parameter for group membership.
   *
   * @var string
   */
  const MEMBER_KEY = 'memberOf';

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = Filter::class;

  /**
   * {@inheritdoc}
   */
  protected $formats = ['api_json'];

  /**
   * The entity condition denormalizer.
   *
   * @var \Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $conditionDenormalizer;

  /**
   * The entity condition group denormalizer.
   *
   * @var \Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $groupDenormalizer;

  /**
   * The field resolver service.
   *
   * @var \Drupal\jsonapi\Context\FieldResolver
   */
  protected $fieldResolver;

  /**
   * {@inheritdoc}
   */
  public function __construct(FieldResolver $field_resolver, DenormalizerInterface $condition_denormalizer, DenormalizerInterface $group_denormalizer) {
    $this->fieldResolver = $field_resolver;
    $this->conditionDenormalizer = $condition_denormalizer;
    $this->groupDenormalizer = $group_denormalizer;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return $type === $this->supportedInterfaceOrClass;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $expanded = $this->expand($data, $context);
    $denormalized = $this->denormalizeItems($expanded);
    return new Filter($denormalized);
  }

  /**
   * Expands any filter parameters using shorthand notation.
   *
   * @param array $original
   *   The unexpanded filter data.
   * @param array $context
   *   The denormalization context.
   *
   * @return array
   *   The expanded filter data.
   */
  protected function expand(array $original, array $context) {
    $expanded = [];
    foreach ($original as $key => $item) {
      // Allow extreme shorthand filters, f.e. `?filter[promote]=1`.
      if (!is_array($item)) {
        $item = [
          EntityConditionNormalizer::VALUE_KEY => $item,
        ];
      }

      // Throw an exception if the query uses the reserved filter id for the
      // root group.
      if ($key == static::ROOT_ID) {
        $msg = sprintf("'%s' is a reserved filter id.", static::ROOT_ID);
        throw new \UnexpectedValueException($msg);
      }

      // Add a memberOf key to all items.
      if (isset($item[static::CONDITION_KEY][static::MEMBER_KEY])) {
        $item[static::MEMBER_KEY] = $item[static::CONDITION_KEY][static::MEMBER_KEY];
        unset($item[static::CONDITION_KEY][static::MEMBER_KEY]);
      }
      elseif (isset($item[static::GROUP_KEY][static::MEMBER_KEY])) {
        $item[static::MEMBER_KEY] = $item[static::GROUP_KEY][static::MEMBER_KEY];
        unset($item[static::GROUP_KEY][static::MEMBER_KEY]);
      }
      else {
        $item[static::MEMBER_KEY] = static::ROOT_ID;
      }

      // Add the filter id to all items.
      $item['id'] = $key;

      // Expands shorthand filters.
      $expanded[$key] = $this->expandItem($key, $item, $context);
    }

    return $expanded;
  }

  /**
   * Expands a filter item in case a shortcut was used.
   *
   * Possible cases for the conditions:
   *   1. filter[uuid][value]=1234.
   *   2. filter[0][condition][field]=uuid&filter[0][condition][value]=1234.
   *   3. filter[uuid][condition][value]=1234.
   *   4. filter[uuid][value]=1234&filter[uuid][group]=my_group.
   *
   * @param string $filter_index
   *   The index.
   * @param array $filter_item
   *   The raw filter item.
   * @param array $context
   *   The denormalization context.
   *
   * @return array
   *   The expanded filter item.
   */
  protected function expandItem($filter_index, array $filter_item, array $context) {
    if (isset($filter_item[EntityConditionNormalizer::VALUE_KEY])) {
      if (!isset($filter_item[EntityConditionNormalizer::PATH_KEY])) {
        $filter_item[EntityConditionNormalizer::PATH_KEY] = $filter_index;
      }

      $filter_item = [
        static::CONDITION_KEY => $filter_item,
        static::MEMBER_KEY => $filter_item[static::MEMBER_KEY],
      ];
    }

    if (!isset($filter_item[static::CONDITION_KEY][EntityConditionNormalizer::OPERATOR_KEY])) {
      $filter_item[static::CONDITION_KEY][EntityConditionNormalizer::OPERATOR_KEY] = '=';
    }

    if (isset($filter_item[static::CONDITION_KEY][EntityConditionNormalizer::PATH_KEY])) {
      $filter_item[static::CONDITION_KEY][EntityConditionNormalizer::PATH_KEY] = $this->fieldResolver->resolveInternalEntityQueryPath(
        $context['entity_type_id'],
        $context['bundle'],
        $filter_item[static::CONDITION_KEY][EntityConditionNormalizer::PATH_KEY]
      );
    }

    return $filter_item;
  }

  /**
   * Denormalizes the given filter items into a single EntityConditionGroup.
   *
   * @param array $items
   *   The normalized entity conditions and groups.
   *
   * @return \Drupal\jsonapi\Query\EntityConditionGroup
   *   A root group containing all the denormalized conditions and groups.
   */
  protected function denormalizeItems(array $items) {
    $root = [
      'id' => static::ROOT_ID,
      static::GROUP_KEY => ['conjunction' => 'AND'],
    ];
    return $this->buildTree($root, $items);
  }

  /**
   * Organizes the flat, normalized filter items into a tree structure.
   *
   * @param array $root
   *   The root of the tree to build.
   * @param array $items
   *   The normalized entity conditions and groups.
   *
   * @return \Drupal\jsonapi\Query\EntityConditionGroup
   *   The entity condition group
   */
  protected function buildTree(array $root, array $items) {
    $id = $root['id'];

    // Recursively build a tree of denormalized conditions and condition groups.
    $members = [];
    foreach ($items as $item) {
      if ($item[static::MEMBER_KEY] == $id) {
        if (isset($item[static::GROUP_KEY])) {
          array_push($members, $this->buildTree($item, $items));
        }
        elseif (isset($item[static::CONDITION_KEY])) {
          $condition = $this->conditionDenormalizer->denormalize(
            $item[static::CONDITION_KEY],
            EntityCondition::class
          );
          array_push($members, $condition);
        }
      }
    }

    $root[static::GROUP_KEY]['members'] = $members;

    // Denormalize the root into a condition group.
    return $this->groupDenormalizer->denormalize(
      $root[static::GROUP_KEY],
      EntityConditionGroup::class
    );
  }

}
