<?php

namespace CloakWP\ACF\Fields;

use CloakWP\ACF\Traits\ConfigurableChoices;
use CloakWP\Core\Enqueue\Stylesheet;
use Extended\ACF\Fields\RadioButton;

/**
 * A Radio Button field for CSS object-position, rendered as a 3×3 icon grid.
 *
 * Choice values match Tailwind object-* utilities / existing image_styles usage:
 * left_top, top, right_top, left, center, right, left_bottom, bottom, right_bottom.
 *
 * Icons are drawn via CSS (not SVG in choice labels) because ACF runs choice HTML
 * through wp_kses, which strips SVG tags.
 */
class ObjectPosition extends RadioButton
{
  use ConfigurableChoices;

  private static bool $hasEnqueuedDependencies = false;

  public static function make(string $label = 'Object Position', string|null $name = null): static
  {
    if (!self::$hasEnqueuedDependencies) {
      Stylesheet::make('cloakwp_acf_object_position_styles')
        ->hooks(['admin_enqueue_scripts', 'enqueue_block_editor_assets'])
        ->src(dirname(plugin_dir_url(__FILE__), 2) . '/css/acf-object-position.css')
        ->version(\WP_ENV === 'development' ? filemtime(dirname(plugin_dir_path(__FILE__), 2) . '/css/acf-object-position.css') : '0.0.1')
        ->enqueue();

      self::$hasEnqueuedDependencies = true;
    }

    return parent::make($label, $name)
      ->choices([
        'left_top' => 'Left Top',
        'top' => 'Top',
        'right_top' => 'Right Top',
        'left' => 'Left',
        'center' => 'Center',
        'right' => 'Right',
        'left_bottom' => 'Left Bottom',
        'bottom' => 'Bottom',
        'right_bottom' => 'Right Bottom',
      ])
      ->default('center')
      ->wrapper(['class' => 'cloakwp-object-position']);
  }
}
