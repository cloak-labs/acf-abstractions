<?php

namespace CloakWP\ACF\Fields;

use Extended\ACF\Fields\{Group, Accordion};

/**
 * A thin wrapper around the Group field that wraps itself in Accordion fields, to make it collapsible with minimal effort.
 */
class CollapsibleGroup extends Group
{
  protected bool $defaultOpen = true;

  public static function make(string $label, string|null $name = null): static
  {
    $self = new static($label, $name);
    $self->wrapper(['class' => 'hide-label']);
    return $self;
  }

  public function defaultOpen(bool $defaultOpen = true): static
  {
    $this->defaultOpen = $defaultOpen;
    return $this;
  }

  public function fields(array $fields): static
  {
    $accordionLabel = $this->settings['label'];
    $accordionStart = Accordion::make($this->settings['label'], $this->settings['name'] . '_accordion')->multiExpand();
    if ($this->defaultOpen) {
      $accordionStart->open();
    }

    $this->settings['sub_fields'] = [
      $accordionStart,
      ...$fields,
      Accordion::make($accordionLabel . ' Endpoint')
        ->endpoint()
        ->multiExpand()
    ];

    return $this;
  }
}
