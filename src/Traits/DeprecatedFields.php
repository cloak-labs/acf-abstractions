<?php

namespace CloakWP\ACF\Traits;

/**
 * A useful trait for custom fields that come with default sub-fields, as it
 * warns users against replacing them by marking the fields method as deprecated.
 */
trait DeprecatedFields
{
  /**
   * @deprecated This field comes with default sub-fields, so it's redundant/not recommended to replace them.
   */
  public function fields(array $fields): static
  {
    parent::fields($fields);
    return $this;
  }
}