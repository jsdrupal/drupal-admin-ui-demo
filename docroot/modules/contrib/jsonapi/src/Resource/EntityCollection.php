<?php

namespace Drupal\jsonapi\Resource;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;

/**
 * Wrapper to normalize collections with multiple entities.
 *
 * @internal
 */
class EntityCollection implements \IteratorAggregate, \Countable {

  /**
   * Entity storage.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities;

  /**
   * Holds a boolean indicating if there is a next page.
   *
   * @var bool
   */
  protected $hasNextPage;

  /**
   * Holds the total count of entities.
   *
   * @var int
   */
  protected $count;

  /**
   * Instantiates a EntityCollection object.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null[] $entities
   *   The entities for the collection.
   */
  public function __construct(array $entities) {
    assert(Inspector::assertAll(function ($entity) {
      return $entity === NULL
        || $entity instanceof EntityInterface
        || $entity instanceof EntityAccessDeniedHttpException;
    }, $entities));
    $this->entities = $entities;
  }

  /**
   * Returns an iterator for entities.
   *
   * @return \ArrayIterator
   *   An \ArrayIterator instance
   */
  public function getIterator() {
    return new \ArrayIterator($this->entities);
  }

  /**
   * Returns the number of entities.
   *
   * @return int
   *   The number of parameters
   */
  public function count() {
    return count($this->entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalCount() {
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function setTotalCount($count) {
    $this->count = $count;
  }

  /**
   * Returns the collection as an array.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The array of entities.
   */
  public function toArray() {
    return $this->entities;
  }

  /**
   * Checks if there is a next page in the collection.
   *
   * @return bool
   *   TRUE if the collection has a next page.
   */
  public function hasNextPage() {
    return (bool) $this->hasNextPage;
  }

  /**
   * Sets the has next page flag.
   *
   * Once the collection query has been executed and we build the entity
   * collection, we now if there will be a next page with extra entities.
   *
   * @param bool $has_next_page
   *   TRUE if the collection has a next page.
   */
  public function setHasNextPage($has_next_page) {
    $this->hasNextPage = (bool) $has_next_page;
  }

}
