<?php

namespace CloakWP\ACF\Fields;

use CloakWP\ACF\Traits\ConfigurableChoices;

/**
 * An ACF select field auto-populated with WordPress menus.
 */
class MenuSelect extends PrepopulatedSelect
{
  use ConfigurableChoices;

  // No hook needed for menus as they're available immediately
  protected ?string $setHook = null;

  protected function setChoices(): void
  {
    $menus = [];
    foreach (wp_get_nav_menus() as $menu) {
      $menus[$menu->term_id] = $menu->name;
    }

    $this->choices($menus);
  }
}
