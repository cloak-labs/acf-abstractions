<?php

namespace CloakWP\ACF\FieldSets;

use CloakWP\ACF\FieldSet;
use Extended\ACF\Fields\{Link, Select};

/**
 * A preset of fields representing a button.
 */
class Button implements FieldSet
{
  public static array $styles = [
    'default' => 'Primary',
    'secondary' => 'Secondary',
    'outline' => 'Outline',
    'ghost' => 'Ghost',
    'link' => 'Link',
    'destructive' => 'Destructive'
  ];

  public static function fields(): array
  {
    $styleChoices = apply_filters('cloakwp/acf/button/styles', self::$styles);

    return [
      Link::make('Link')
        ->column(50),
      Select::make('Style')
        ->choices($styleChoices)
        ->column(50)
    ];
  }
}
