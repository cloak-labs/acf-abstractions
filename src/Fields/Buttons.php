<?php

namespace CloakWP\ACF\Fields;

use Extended\ACF\Fields\Repeater;
use CloakWP\ACF\FieldSets\Button;
use CloakWP\ACF\Traits\{ConfigurableFields, DeprecatedFields};

class Buttons extends Repeater
{
  use ConfigurableFields;
  use DeprecatedFields;

  public static function make(string $label, string|null $name = null): static
  {
    $self = new static($label, $name);

    // Apply default settings:
    return $self->fields(Button::fields())
      ->layout('block')
      ->button('Add button');
  }
}