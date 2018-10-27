<?php

namespace Drupal\jsonapi\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

/**
 * Overrides the Symfony serializer to cordon off our incompatible normalizers.
 *
 * This service is for *internal* use only. It is not suitable for *any* reuse.
 * Backwards compatibility is in no way guaranteed and will almost certainly be
 * broken in the future.
 *
 * @link https://www.drupal.org/project/jsonapi/issues/2923779#comment-12407443
 *
 * @internal
 */
final class Serializer extends SymfonySerializer {

  /**
   * A normalizer to fall back on when JSON API cannot normalize an object.
   *
   * @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $fallbackNormalizer;

  /**
   * Adds a secondary normalizer.
   *
   * This normalizer will be attempted when JSON API has no applicable
   * normalizer.
   *
   * @param \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer
   *   The secondary normalizer.
   */
  public function setFallbackNormalizer(NormalizerInterface $normalizer) {
    $this->fallbackNormalizer = $normalizer;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    if ($this->selfSupportsNormalization($data, $format)) {
      return parent::normalize($data, $format, $context);
    }
    if ($this->fallbackNormalizer->supportsNormalization($data, $format)) {
      return $this->fallbackNormalizer->normalize($data, $format, $context);
    }
    return parent::normalize($data, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $type, $format = NULL, array $context = []) {
    if ($this->selfSupportsDenormalization($data, $type, $format)) {
      return parent::denormalize($data, $type, $format, $context);
    }
    return $this->fallbackNormalizer->denormalize($data, $type, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $this->selfSupportsNormalization($data, $format) || $this->fallbackNormalizer->supportsNormalization($data, $format);
  }

  /**
   * Checks whether this class alone supports normalization.
   *
   * @param mixed $data
   *   Data to normalize.
   * @param string $format
   *   The format being (de-)serialized from or into.
   *
   * @return bool
   *   Whether this class supports normalization for the given data.
   */
  private function selfSupportsNormalization($data, $format = NULL) {
    return parent::supportsNormalization($data, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return $this->selfSupportsDenormalization($data, $type, $format) || $this->fallbackNormalizer->supportsDenormalization($data, $type, $format);
  }

  /**
   * Checks whether this class alone supports denormalization.
   *
   * @param mixed $data
   *   Data to denormalize from.
   * @param string $type
   *   The class to which the data should be denormalized.
   * @param string $format
   *   The format being deserialized from.
   *
   * @return bool
   *   Whether this class supports normalization for the given data and type.
   */
  private function selfSupportsDenormalization($data, $type, $format = NULL) {
    return parent::supportsDenormalization($data, $type, $format);
  }

}
