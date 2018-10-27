<?php

namespace Drupal\jsonapi\Exception;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Enhances the access denied exception with information about the entity.
 *
 * @internal
 */
class EntityAccessDeniedHttpException extends HttpException {

  use DependencySerializationTrait;

  /**
   * The error which caused the 403.
   *
   * The error contains:
   *   - entity: The entity which the current user doens't have access to.
   *   - pointer: A path in the JSON API response structure pointing to the
   *     entity.
   *   - reason: (Optional) An optional reason for this failure.
   *
   * @var array
   */
  protected $error = [];

  /**
   * EntityAccessDeniedHttpException constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity, or NULL when an entity is being created.
   * @param \Drupal\Core\Access\AccessResultInterface $entity_access
   *   The access result.
   * @param string $pointer
   *   (optional) The pointer.
   * @param string $messsage
   *   (Optional) The display to display.
   * @param \Exception|null $previous
   *   The previous exception.
   * @param array $headers
   *   The headers.
   * @param int $code
   *   The code.
   */
  public function __construct($entity, AccessResultInterface $entity_access, $pointer, $messsage = 'The current user is not allowed to GET the selected resource.', \Exception $previous = NULL, array $headers = [], $code = 0) {
    assert(is_null($entity) || $entity instanceof EntityInterface);
    parent::__construct(403, $messsage, $previous, $headers, $code);

    $error = [
      'entity' => $entity,
      'pointer' => $pointer,
      'reason' => NULL,
    ];
    if ($entity_access instanceof AccessResultReasonInterface) {
      $error['reason'] = $entity_access->getReason();
    }
    $this->error = $error;
  }

  /**
   * Returns the error.
   *
   * @return array
   *   The error.
   */
  public function getError() {
    return $this->error;
  }

}
