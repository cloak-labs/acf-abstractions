<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\FieldTree;

use CloakWP\ACF\{Block, BlockRegistry};
use CloakWP\ACF\Fields\InnerBlocks;
use Extended\ACF\Fields\{Accordion, Group, Layout, Text};
use PHPUnit\Framework\TestCase;

final class InnerBlocksTest extends TestCase
{
    protected function setUp(): void
    {
        (new \ReflectionProperty(BlockRegistry::class, 'instance'))->setValue(null, null);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(BlockRegistry::class, 'instance'))->setValue(null, null);
    }

    private function registerBlock(array $fields, string $name = 'acf/example'): void
    {
        BlockRegistry::getInstance()->addBlock(new class($fields, $name) extends Block {
            public function __construct(private array $testFields, private string $testName) {}
            public function getFieldGroupSettings(): array
            {
                return ['name' => $this->testName, 'key' => 'group_example', 'title' => 'Example', 'fields' => $this->testFields];
            }
        });
    }

    public function testNestedExportsKeepSourceFieldsReusableAndExcludeIncompatibleFields(): void
    {
        $text = Text::make('Title', 'title');
        $nested = Group::make('Content', 'content')->fields([
            $text, InnerBlocks::make('Children', 'children'),
        ]);
        $this->registerBlock([Accordion::make('Start'), $nested, Accordion::make('End')]);
        $first = InnerBlocks::make('Blocks', 'first')->toArray('group_first');
        $second = InnerBlocks::make('Blocks', 'second')->toArray('group_second');
        foreach ([$first, $second] as $export) {
            $fields = $export['layouts'][0]['sub_fields'];
            self::assertSame(['content', 'className'], array_column($fields, 'name'));
            self::assertSame(['title'], array_column($fields[0]['sub_fields'], 'name'));
        }
        self::assertNotSame($first['layouts'][0]['sub_fields'][0]['sub_fields'][0]['key'], $second['layouts'][0]['sub_fields'][0]['sub_fields'][0]['key']);
        self::assertSame($text, $nested->settings['sub_fields'][0]);
        self::assertCount(2, $nested->settings['sub_fields']);
        self::assertArrayNotHasKey('key', $text->settings);
        $direct = $nested->toArray('group_direct');
        self::assertSame(['title', 'children'], array_column($direct['sub_fields'], 'name'));
    }

    public function testExplicitLayoutsRemainAvailableAfterAnInnerBlocksExport(): void
    {
        $this->registerBlock([Text::make('Title', 'title')]);
        $original = Layout::make('Custom', 'custom')->fields([Text::make('Text', 'text')]);
        $field = InnerBlocks::make('Blocks', 'blocks')->layouts([$original]);
        $export = $field->toArray('group_explicit');
        self::assertSame('acf/example', $export['layouts'][0]['name']);
        self::assertSame([$original], $field->settings['layouts']);
    }

    public function testIncludesExcludesAndSelfNestingKeepTheirLayoutContract(): void
    {
        $this->registerBlock([Text::make('Title', 'title')], 'acf/one');
        $this->registerBlock([Text::make('Title', 'title')], 'acf/two');
        $export = InnerBlocks::make('Blocks', 'blocks')->include(['acf/one'])->toArray('group_include');
        self::assertSame(['acf/one'], array_column($export['layouts'], 'name'));
        $excluded = InnerBlocks::make('Blocks', 'blocks')->exclude(['acf/one', 'acf/two'])->toArray('group_exclude');
        self::assertSame([], $excluded['layouts']);
        $self = InnerBlocks::make('Blocks', 'blocks')->toArray('group_example_parent');
        self::assertSame([], $self['layouts']);
    }
}
