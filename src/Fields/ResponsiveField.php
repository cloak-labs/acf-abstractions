<?php

namespace CloakWP\ACF\Fields;

use CloakWP\Core\Enqueue\Stylesheet;
use CloakWP\Core\Utils;
use Extended\ACF\Fields\Field;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Tab;
use Extended\ACF\Key;

// Define the Breakpoint Enum
enum Breakpoint: string
{
  case MOBILE = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>';
  case TABLET = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>';
  case TABLET_WIDE = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="wider"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>';
  case LAPTOP = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/></svg>';
  case DESKTOP = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" /></svg>';
  case DESKTOP_WIDE = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="wider"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" /></svg>';
}

class ResponsiveField
{
  private static $hasEnqueuedDependencies = false;

  /**
   * Create a new ResponsiveField instance.
   *
   * @param Field $field The field to be responsive.
   * @param Breakpoint[] $breakpoints The breakpoints to include in the responsive field.
   * @return static The new ResponsiveField instance.
   */
  public static function make(
    Field $field,
    array $breakpoints = [
      Breakpoint::MOBILE,
      Breakpoint::TABLET,
      Breakpoint::TABLET_WIDE,
      Breakpoint::LAPTOP,
      Breakpoint::DESKTOP,
      Breakpoint::DESKTOP_WIDE,
    ]
  ): Group {
    if (!$field) {
      throw new \InvalidArgumentException('A Field must be provided to ResponsiveTabs.');
    }

    if (!self::$hasEnqueuedDependencies) {
      Stylesheet::make("cloakwp_acf_responsive_field_styles")
        ->hook('enqueue_block_editor_assets')
        ->src(dirname(plugin_dir_url(__FILE__), 2) . '/css/acf-responsive-field.css')
        ->version(\WP_ENV === "development" ? filemtime(dirname(plugin_dir_path(__FILE__), 2) . '/css/acf-responsive-field.css') : '0.0.1')
        ->enqueue();

      self::$hasEnqueuedDependencies = true;
    }

    $innerFields = [];
    $fieldName = null;
    foreach ($breakpoints as $bp) {
      $label = strtolower($bp->name);
      $icon = $bp->value;
      $name = Key::sanitize($label);
      $innerFields[] = Tab::make($icon, $name . '_tab');

      $fieldAtBreakpoint = Utils::deep_copy($field);

      $fieldReflection = new \ReflectionClass($fieldAtBreakpoint);
      $property = $fieldReflection->getProperty('settings');
      $property->setAccessible(true);
      $settings = $property->getValue($fieldAtBreakpoint);
      if (!$fieldName) {
        $fieldName = $settings['name'];
      }
      $settings['name'] = $name;
      $property->setValue($fieldAtBreakpoint, $settings);

      $innerFields[] = $fieldAtBreakpoint;
    }

    $innerFields[] = Tab::make('')->endpoint();

    return Group::make('', $fieldName)->fields($innerFields)->wrapper(['class' => 'acf-responsive-field']);
  }
}