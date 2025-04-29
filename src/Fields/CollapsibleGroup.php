<?php

namespace CloakWP\ACF\Fields;

use Extended\ACF\Fields\{Group, Accordion};

/**
 * A thin wrapper around the Group field that wraps itself in Accordion fields, to make it collapsible with minimal effort.
 */
class CollapsibleGroup extends Group
{
  public static function make(string $label, string|null $name = null): static
  {
    $self = new static($label, $name);
    $self->wrapper(['class' => 'hide-label']);
    return $self;
  }

  public function fields(array $fields): static
  {
    $accordionLabel = $this->settings['label'];

    $this->settings['sub_fields'] = [
      Accordion::make($this->settings['label'], $this->settings['name'] . '_accordion')
        ->open()
        ->multiExpand(),
      ...$fields,
      Accordion::make($accordionLabel . ' Endpoint')
        ->endpoint()
        ->multiExpand()
    ];

    return $this;
  }
}