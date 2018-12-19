<?php

namespace Drupal\jsonapi\Query;

/**
 * A condition object for the EntityQuery.
 *
 * @internal
 */
class EntityCondition {

  /**
   * The allowed condition operators.
   *
   * @var string[]
   */
  public static $allowedOperators = [
    '=', '<>',
    '>', '>=', '<', '<=',
    'STARTS_WITH', 'CONTAINS', 'ENDS_WITH',
    'IN', 'NOT IN',
    'BETWEEN', 'NOT BETWEEN',
    'IS NULL', 'IS NOT NULL',
  ];

  /**
   * The field to be evaluated.
   *
   * @var string
   */
  protected $field;

  /**
   * The condition operator.
   *
   * @var string
   */
  protected $operator;

  /**
   * The value against which the field should be evaluated.
   *
   * @var mixed
   */
  protected $value;

  /**
   * Constructs a new EntityCondition object.
   */
  public function __construct($field, $value, $operator = NULL) {
    $this->field = $field;
    $this->value = $value;
    $this->operator = ($operator) ? $operator : '=';
  }

  /**
   * The field to be evaluated.
   *
   * @return string
   *   The field upon which to evaluate the condition.
   */
  public function field() {
    return $this->field;
  }

  /**
   * The comparison operator to use for the evaluation.
   *
   * For a list of allowed operators:
   *
   * @see \Drupal\jsonapi\Query\EntityCondition::allowedOperators
   *
   * @return string
   *   The condition operator.
   */
  public function operator() {
    return $this->operator;
  }

  /**
   * The value against which the condition should be evaluated.
   *
   * @return mixed
   *   The condition comparison value.
   */
  public function value() {
    return $this->value;
  }

}
