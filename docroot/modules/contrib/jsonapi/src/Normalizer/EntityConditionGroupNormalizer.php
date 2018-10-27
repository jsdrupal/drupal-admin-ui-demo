<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\jsonapi\Query\EntityConditionGroup;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * The normalizer used for entity conditions.
 *
 * @internal
 */
class EntityConditionGroupNormalizer implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = EntityConditionGroup::class;

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
    return new EntityConditionGroup($data['conjunction'], $data['members']);
  }

}
