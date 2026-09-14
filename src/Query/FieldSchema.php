<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

use CloakWP\ACF\Fields\PostTypeSelect;
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Field;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Number;
use Extended\ACF\Fields\RadioButton;
use Extended\ACF\Fields\Relationship;
use Extended\ACF\Fields\Select;
use Extended\ACF\Fields\Taxonomy;

final class FieldSchema
{
  /**
   * @return list<Field>
   */
  public static function fields(Definition $definition): array
  {
    $fields = [
      RadioButton::make('Selection', 'selection')
        ->choices([
          'all' => 'Automatic',
          'some' => 'Manual',
        ])
        ->default('all')
        ->layout('horizontal')
        ->column(50),
    ];

    if ($definition->hasLimitField) {
      $limit = Number::make('Limit', 'limit')
        ->min(1)
        ->max($definition->maxPosts)
        ->helperText(
          $definition->unlimited
            ? 'Maximum number of posts to return. Leave empty to return all.'
            : 'Maximum number of posts to return.',
        )
        ->column(50)
        ->conditionalLogic([
          ConditionalLogic::where('selection', '!=', 'some'),
        ]);

      if ($definition->defaultLimit !== null) {
        $limit->default($definition->defaultLimit);
      }

      $fields[] = $limit;
    }

    $postTypeField = self::postTypeField($definition);
    if ($postTypeField !== null) {
      $fields[] = $postTypeField;
    }

    if ($definition->hasOrderingField) {
      $fields = [...$fields, ...self::orderingFields($definition)];
    }

    if ($definition->taxonomies !== []) {
      $fields[] = self::taxonomiesField($definition);
    }

    $fields[] = self::postsField($definition);

    return $fields;
  }

  private static function postTypeField(Definition $definition): ?Field
  {
    if ($definition->policy === PostTypePolicy::Locked) {
      return null;
    }

    $field = $definition->policy === PostTypePolicy::Allowlist
      ? Select::make('Post Type', 'post_type')
        ->choices(self::postTypeChoices($definition->allowedPostTypes))
      : PostTypeSelect::make('Post Type', 'post_type');

    return $field
      ->multiple()
      ->stylized()
      ->nullable()
      ->helperText('Leave empty to use the default post types for this query.')
      ->conditionalLogic([
        ConditionalLogic::where('selection', '!=', 'some'),
      ]);
  }

  /**
   * @return list<Field>
   */
  private static function orderingFields(Definition $definition): array
  {
    $defaultKey = array_key_first($definition->defaultOrderby) ?? array_key_first($definition->orderbyChoices);
    $defaultDirection = strtoupper((string) (array_values($definition->defaultOrderby)[0] ?? 'DESC'));
    if ($defaultDirection !== 'ASC') {
      $defaultDirection = 'DESC';
    }

    $orderby = Select::make('Order By', 'orderby')
      ->choices($definition->orderbyChoices)
      ->column(50)
      ->conditionalLogic([
        ConditionalLogic::where('selection', '!=', 'some'),
      ]);

    if (is_string($defaultKey) && $defaultKey !== 'rand') {
      $orderby->default($defaultKey);
    } elseif (is_string($defaultKey)) {
      $orderby->default($defaultKey);
    }

    $order = Select::make('Order', 'order')
      ->choices([
        'ASC' => 'Ascending',
        'DESC' => 'Descending',
      ])
      ->default($defaultDirection)
      ->column(50)
      ->conditionalLogic([
        ConditionalLogic::where('selection', '!=', 'some')->and('orderby', '!=', 'rand'),
      ]);

    return [$orderby, $order];
  }

  private static function taxonomiesField(Definition $definition): Field
  {
    $subFields = [];

    foreach ($definition->taxonomies as $taxonomy) {
      $label = $definition->taxonomyLabels[$taxonomy] ?? self::taxonomyLabel($taxonomy);
      $subFields[] = Taxonomy::make($label, $taxonomy)
        ->taxonomy($taxonomy)
        ->appearance('checkbox')
        ->format('id')
        ->load(false)
        ->save(false)
        ->create(false);
    }

    return Group::make('Taxonomies', 'taxonomies')
      ->fields($subFields)
      ->layout('block')
      ->conditionalLogic([
        ConditionalLogic::where('selection', '!=', 'some'),
      ]);
  }

  private static function postsField(Definition $definition): Field
  {
    $relationship = Relationship::make('Posts', 'posts')
      ->helperText('Select the posts to display. Drag to set their order.')
      ->filters($definition->policy === PostTypePolicy::Locked ? ['search'] : ['search', 'post_type'])
      ->format('id')
      ->elements(['featured_image'])
      ->conditionalLogic([
        ConditionalLogic::where('selection', '==', 'some'),
      ]);

    if ($definition->policy !== PostTypePolicy::Any && $definition->allowedPostTypes !== []) {
      $relationship->postTypes($definition->allowedPostTypes);
    }

    return $relationship;
  }

  /**
   * @param list<string> $slugs
   * @return array<string, string>
   */
  private static function postTypeChoices(array $slugs): array
  {
    $choices = [];
    foreach ($slugs as $slug) {
      $choices[$slug] = self::postTypeLabel($slug);
    }

    return $choices;
  }

  private static function postTypeLabel(string $slug): string
  {
    if (function_exists('get_post_type_object')) {
      $object = get_post_type_object($slug);
      if (is_object($object) && isset($object->labels->singular_name)) {
        return (string) $object->labels->singular_name;
      }
    }

    return $slug;
  }

  private static function taxonomyLabel(string $slug): string
  {
    if (function_exists('get_taxonomy')) {
      $taxonomy = get_taxonomy($slug);
      if (is_object($taxonomy) && isset($taxonomy->labels->singular_name)) {
        return (string) $taxonomy->labels->singular_name;
      }
    }

    return $slug;
  }
}
