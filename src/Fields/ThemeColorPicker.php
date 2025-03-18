<?php

namespace CloakWP\ACF\Fields;

use CloakWP\Core\Enqueue\Stylesheet;
use Extended\ACF\Fields\RadioButton;
use WP_Theme_JSON_Resolver;

/**
 * An auto-populated ACF Radio Button field that allows you to select a color from the active theme's theme.json color palette.
 */
class ThemeColorPicker extends RadioButton
{
  private static $hasEnqueuedDependencies = false;

  // we override inherited `make` in order to set default colors when include() isn't called/specified
  public static function make(string $label, string|null $name = null): static
  {
    if (!self::$hasEnqueuedDependencies) {
      // add required CSS styles for the color picker, but only ONCE (no matter how many ThemeColorPicker fields are created)
      $palette = self::getThemeColorPalette();
      add_action('admin_head', function () use ($palette) {
        $themeColorPickerCSS = '';
        if (!empty($palette)) {
          foreach ($palette as $color) {
            $themeColorPickerCSS .= ".cloakwp-theme-color-picker .acf-radio-list li label input[type='radio'][value='{$color['slug']}'] { background-color: var(--wp--preset--color--{$color['slug']}); }";
          }
        }
        echo "<style id='themeColorPickerACF'>{$themeColorPickerCSS}</style>";
      });

      Stylesheet::make("cloakwp_theme_color_picker_styles")
        ->hooks(["admin_enqueue_scripts"])
        ->src(dirname(plugin_dir_url(__FILE__)) . '/css/acf-theme-color-picker.css')
        ->version(\WP_ENV === "development" ? filemtime(dirname(plugin_dir_path(__FILE__), 2) . '/css/acf-theme-color-picker.css') : '0.0.1')
        ->enqueue();

      self::$hasEnqueuedDependencies = true;
    }

    $self = new static($label, $name);
    $self->include(); // set defaults
    return $self;
  }

  public function include(array $enabledColors = []): self
  {
    $final_colors = $this->filterColors($enabledColors);
    $this->setFinalColors($final_colors);
    return $this;
  }

  public function exclude(array $excludedColors = []): self
  {
    $final_colors = $this->filterColors($excludedColors, true);
    $this->setFinalColors($final_colors);
    return $this;
  }

  private function filterColors(array $filterColors = [], bool $exclude = false): array
  {
    $color_palette = self::getThemeColorPalette();
    $final_colors = [];

    // if there are colors in the $color_palette array
    if (!empty($color_palette)) {
      $noFilterColors = empty($filterColors);

      // loop over each color and create option
      foreach ($color_palette as $color) {
        $includeCondition = $exclude ? !in_array($color['slug'], $filterColors) : in_array($color['slug'], $filterColors);

        // filter colors based on whether they've been included or excluded
        if ($noFilterColors || $includeCondition) {
          $final_colors[$color['slug']] = $color['name'];
        }
      }
    }

    return $final_colors;
  }

  private function setFinalColors(array $final_colors): void
  {
    $this->settings['choices'] = $final_colors;
    $this->settings['wrapper']['class'] = 'cloakwp-theme-color-picker';
  }

  private static function getThemeColorPalette(): array
  {
    $palette = [];

    // check if theme.json is being used and if so, grab the settings
    if (class_exists('WP_Theme_JSON_Resolver')) {
      $settings = WP_Theme_JSON_Resolver::get_theme_data()->get_settings();

      if (isset($settings['color']['palette']['theme'])) {
        $palette = $settings['color']['palette']['theme'];
      }
    } else {
      throw new \Exception('Theme.json is not being used, and is required for the ThemeColorPicker field to work. Please enable the theme.json feature in your theme.');
    }

    return $palette;
  }
}
