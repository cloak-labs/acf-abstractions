<?php

namespace CloakWP\ACF;

/**
 * Extend this class to create a reusable set of fields.
 */
interface FieldSet
{
  /**
   * Get the array offields for the field set.
   *
   * @return \Extended\ACF\Fields\Field[]
   */
  public static function fields(): array;
}
