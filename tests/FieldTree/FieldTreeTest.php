<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests;

use CloakWP\ACF\FieldTree;
use CloakWP\ACF\Traits\ConfigurableFields;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Text;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigurableGroup extends Group
{
  use ConfigurableFields;
}

final class FieldTreeTest extends TestCase
{
  public function testFindReturnsNestedFieldByDottedName(): void
  {
    $root = ConfigurableGroup::make('Layout', 'layout')->fields([
      ConfigurableGroup::make('Footer', 'footer')->fields([
        Text::make('Heading', 'heading'),
      ]),
    ]);

    $heading = FieldTree::find($root, 'footer.heading');

    $this->assertSame('heading', $heading->settings['name']);
  }

  public function testAppendAndRemoveFieldsOnNamedGroup(): void
  {
    $root = ConfigurableGroup::make('Layout', 'layout')->fields([
      ConfigurableGroup::make('Footer', 'footer')->fields([
        Text::make('Heading', 'heading'),
      ]),
    ]);

    $root->field('footer')->appendFields([
      Text::make('Eyebrow', 'eyebrow'),
    ]);
    $root->field('footer')->removeFields(['heading']);

    $names = array_map(
      static fn($field) => $field->settings['name'],
      $root->field('footer')->settings['sub_fields'],
    );

    $this->assertSame(['eyebrow'], $names);
  }

  public function testFindThrowsWhenSegmentIsMissing(): void
  {
    $root = ConfigurableGroup::make('Layout', 'layout')->fields([
      ConfigurableGroup::make('Footer', 'footer')->fields([]),
    ]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Field [cta] not found on [footer].');

    FieldTree::find($root, 'footer.cta');
  }
}
