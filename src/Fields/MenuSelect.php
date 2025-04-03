<?php

namespace CloakWP\ACF\Fields;

/**
 * An ACF select field auto-populated with WordPress menus.
 */
class MenuSelect extends PrepopulatedSelect
{
  protected function setChoices(): void
  {
    $menus = [];
    foreach (wp_get_nav_menus() as $menu) {
      $menus[$menu->term_id] = $menu->name;
    }

    $this->choices($menus);
  }
}
