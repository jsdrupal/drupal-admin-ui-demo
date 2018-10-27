<?php

namespace Drupal\jsonapi\Query;

use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Gathers information about the filter parameter.
 *
 * @internal
 */
class Filter {

  /**
   * The JSON API filter key name.
   *
   * @var string
   */
  const KEY_NAME = 'filter';

  /**
   * The root condition group.
   *
   * @var string
   */
  protected $root;

  /**
   * Constructs a new Filter object.
   *
   * @param \Drupal\jsonapi\Query\EntityConditionGroup $root
   *   An entity condition group which can be applied to an entity query.
   */
  public function __construct(EntityConditionGroup $root) {
    $this->root = $root;
  }

  /**
   * Gets the root condition group.
   */
  public function root() {
    return $this->root;
  }

  /**
   * Applies the root condition to the given query.
   *
   * @param \Drupal\Entity\Query\QueryInterface $query
   *   The query for which the condition should be constructed.
   *
   * @return \Drupal\Entity\Query\ConditionInterface
   *   The compiled entity query condition.
   */
  public function queryCondition(QueryInterface $query) {
    $condition = $this->buildGroup($query, $this->root());
    return $condition;
  }

  /**
   * Applies the root condition to the given query.
   *
   * @param \Drupal\Entity\Query\QueryInterface $query
   *   The query to which the filter should be applied.
   * @param \Drupal\Entity\Query\EntityConditionGroup $condition_group
   *   The condition group to build.
   *
   * @return \Drupal\Entity\Query\QueryInterface
   *   The query with the filter applied.
   */
  protected function buildGroup(QueryInterface $query, EntityConditionGroup $condition_group) {
    // Create a condition group using the original query.
    switch ($condition_group->conjunction()) {
      case 'AND':
        $group = $query->andConditionGroup();
        break;

      case 'OR':
        $group = $query->orConditionGroup();
        break;
    }

    // Get all children of the group.
    $members = $condition_group->members();

    foreach ($members as $member) {
      // If the child is simply a condition, add it to the new group.
      if ($member instanceof EntityCondition) {
        if ($member->operator() == 'IS NULL') {
          $group->notExists($member->field());
        }
        elseif ($member->operator() == 'IS NOT NULL') {
          $group->exists($member->field());
        }
        else {
          $group->condition($member->field(), $member->value(), $member->operator());
        }
      }
      // If the child is a group, then recursively construct a sub group.
      elseif ($member instanceof EntityConditionGroup) {
        // Add the subgroup to this new group.
        $subgroup = $this->buildGroup($query, $member);
        $group->condition($subgroup);
      }
    }

    // Return the constructed group so that it can be added to the query.
    return $group;
  }

}
