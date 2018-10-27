<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\jsonapi\Query\EntityCondition;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The normalizer used for entity conditions.
 *
 * @internal
 */
class EntityConditionNormalizer implements DenormalizerInterface {

  /**
   * The field key in the filter condition: filter[lorem][condition][<field>].
   *
   * @var string
   */
  const PATH_KEY = 'path';

  /**
   * The value key in the filter condition: filter[lorem][condition][<value>].
   *
   * @var string
   */
  const VALUE_KEY = 'value';

  /**
   * The operator key in the condition: filter[lorem][condition][<operator>].
   *
   * @var string
   */
  const OPERATOR_KEY = 'operator';

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = EntityCondition::class;

  /**
   * {@inheritdoc}
   */
  protected $formats = ['api_json'];

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
    $this->validate($data);
    $field = $data[static::PATH_KEY];
    $value = (isset($data[static::VALUE_KEY])) ? $data[static::VALUE_KEY] : NULL;
    $operator = (isset($data[static::OPERATOR_KEY])) ? $data[static::OPERATOR_KEY] : NULL;
    return new EntityCondition($field, $value, $operator);
  }

  /**
   * Validates the filter has the required fields.
   */
  protected function validate($data) {
    $valid_key_combinations = [
      [static::PATH_KEY, static::VALUE_KEY],
      [static::PATH_KEY, static::OPERATOR_KEY],
      [static::PATH_KEY, static::VALUE_KEY, static::OPERATOR_KEY],
    ];

    $given_keys = array_keys($data);
    $valid_key_set = array_reduce($valid_key_combinations, function ($valid, $set) use ($given_keys) {
      return ($valid) ? $valid : count(array_diff($set, $given_keys)) === 0;
    }, FALSE);

    $has_operator_key = isset($data[static::OPERATOR_KEY]);
    $has_path_key = isset($data[static::PATH_KEY]);
    $has_value_key = isset($data[static::VALUE_KEY]);

    if (!$valid_key_set) {
      // Try to provide a more specific exception is a key is missing.
      if (!$has_operator_key) {
        if (!$has_path_key) {
          throw new BadRequestHttpException("Filter parameter is missing a '" . static::PATH_KEY . "' key.");
        }
        if (!$has_value_key) {
          throw new BadRequestHttpException("Filter parameter is missing a '" . static::VALUE_KEY . "' key.");
        }
      }

      // Catchall exception.
      $reason = "You must provide a valid filter condition. Check that you have set the required keys for your filter.";
      throw new BadRequestHttpException($reason);
    }

    if ($has_operator_key) {
      $operator = $data[static::OPERATOR_KEY];
      if (!in_array($operator, EntityCondition::$allowedOperators)) {
        $reason = "The '" . $operator . "' operator is not allowed in a filter parameter.";
        throw new BadRequestHttpException($reason);
      }

      if (in_array($operator, ['IS NULL', 'IS NOT NULL']) && $has_value_key) {
        $reason = "Filters using the '" . $operator . "' operator should not provide a value.";
        throw new BadRequestHttpException($reason);
      }
    }
  }

}
