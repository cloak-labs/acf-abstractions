<?php

namespace CloakWP\ACF\Traits;

trait ConfigurableFields
{
  public function appendFields(array $fields): static
  {
    $this->settings['sub_fields'] = array_merge($this->settings['sub_fields'], $fields);
    return $this;
  }

  public function prependFields(array $fields): static
  {
    $this->settings['sub_fields'] = array_merge($fields, $this->settings['sub_fields']);
    return $this;
  }

  // TODO: `removeFields`, 
}