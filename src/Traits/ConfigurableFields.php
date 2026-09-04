<?php

namespace CloakWP\ACF\Traits;

use CloakWP\ACF\FieldTree;
use Extended\ACF\Fields\Field;

trait ConfigurableFields
{
  public function field(string $path): Field
  {
    return FieldTree::find($this, $path);
  }

  public function appendFields(array $fields): static
  {
    $this->settings['sub_fields'] = array_merge($this->settings['sub_fields'] ?? [], $fields);
    return $this;
  }

  public function prependFields(array $fields): static
  {
    $this->settings['sub_fields'] = array_merge($fields, $this->settings['sub_fields'] ?? []);
    return $this;
  }

  /**
   * @param list<string> $names ACF field names to remove from this group's sub-fields
   */
  public function removeFields(array $names): static
  {
    $this->settings['sub_fields'] = array_values(array_filter(
      $this->settings['sub_fields'] ?? [],
      static fn($field): bool => !in_array($field->settings['name'] ?? '', $names, true),
    ));
    return $this;
  }
}
