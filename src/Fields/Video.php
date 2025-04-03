<?php

namespace CloakWP\ACF\Fields;

use CloakWP\ACF\Traits\{ConfigurableFields, DeprecatedFields};
use CloakWP\ACF\FieldSets\Video as VideoFieldSet;
use Extended\ACF\Fields\Group;

class Video extends Group
{
  use ConfigurableFields;
  use DeprecatedFields;

  public static function make(string $label = 'Video', string|null $name = null): static
  {
    $self = new static($label, $name);

    return $self->fields(VideoFieldSet::fields());
  }
}
