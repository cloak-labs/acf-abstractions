<?php

declare(strict_types=1);

// wp eval-file tests/Integration/LazyInnerBlocksWordPress.php --use-include --url=<site>

use CloakWP\ACF\{Block, BlockRegistry};
use CloakWP\ACF\Fields\InnerBlocks;
use Extended\ACF\Fields\{Accordion, Group, Text};
use Extended\ACF\Location;

if (!function_exists('acf_get_field')) {
    throw new RuntimeException('Run this check through WP-CLI with ACF active.');
}

$checks = 0;
$same = static function ($expected, $actual, string $message) use (&$checks): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': ' . var_export([$expected, $actual], true));
    }
    $checks++;
};

$title = Text::make('Title', 'title');
$nested = Group::make('Nested', 'nested')->fields([
    $title, InnerBlocks::make('Excluded', 'excluded'),
]);
BlockRegistry::getInstance()->addBlock(new class($nested) extends Block {
    public function __construct(private Group $nested) {}
    public function getFieldGroupSettings(): array
    {
        return [
            'name' => 'acf/lazy-test-child', 'key' => 'block_lazy_test_child',
            'title' => 'Lazy test child',
            'fields' => [Accordion::make('Start'), $this->nested, Accordion::make('End')],
        ];
    }
});

$first = InnerBlocks::make('First', 'first')->include(['acf/lazy-test-child']);
$second = InnerBlocks::make('Second', 'second')->include(['acf/lazy-test-child']);
$settings = register_extended_field_group([
    'title' => 'Lazy InnerBlocks test',
    'key' => 'lazy_innerblocks_test',
    'location' => [Location::where('post_type', 'page')],
    'fields' => [$first, $second],
]);
[$firstExport, $secondExport] = $settings['fields'];
$same([], $firstExport['layouts'], 'Registration expanded the first field');
$same([], $secondExport['layouts'], 'Registration expanded the second field');
$same([], acf_get_local_fields($firstExport['key']), 'Unused children were registered');

// Later configuration edits must not alter the definition already registered.
$first->exclude(['acf/lazy-test-child']);
$loaded = acf_get_field($firstExport['key']);
$same(['acf/lazy-test-child'], array_column($loaded['layouts'], 'name'), 'Deferred configuration changed');
$layout = $loaded['layouts'][0];
$same(['nested', 'className'], array_column($layout['sub_fields'], 'name'), 'Child ordering changed');
$same(['title'], array_column($layout['sub_fields'][0]['sub_fields'], 'name'), 'Excluded children leaked');
$same(2, count(acf_get_local_fields($firstExport['key'])), 'Layout children were not imported');
$same(2, count($nested->settings['sub_fields']), 'Source fields were mutated');

$count = count(acf_get_local_store('fields')->get());
$same($loaded, acf_get_field($firstExport['key']), 'Repeated loading changed the field');
$same($count, count(acf_get_local_store('fields')->get()), 'Repeated loading expanded again');
$same([], acf_get_local_fields($secondExport['key']), 'Loading one field expanded another');
$other = acf_get_field($secondExport['key']);
$same(false, $layout['key'] === $other['layouts'][0]['key'], 'Parent-specific layout keys collided');
$same(false, $layout['sub_fields'][0]['key'] === $other['layouts'][0]['sub_fields'][0]['key'], 'Parent-specific field keys collided');

$group = $layout['sub_fields'][0];
$css = $layout['sub_fields'][1];
$value = [[
    'acf_fc_layout' => 'acf/lazy-test-child',
    $group['key'] => [$group['sub_fields'][0]['key'] => 'Hello'],
    $css['key'] => 'p-4',
]];
$formatted = acf_format_value($value, 0, $loaded);
$same('acf/lazy-test-child', $formatted[0]['name'], 'Block formatting lost the layout');
$same('Hello', $formatted[0]['data']['nested']['title'], 'Nested values changed');
$same('p-4', $formatted[0]['data']['className'], 'CSS classes changed');

$rendered = $loaded;
$rendered['value'] = $value;
ob_start();
acf_render_field_wrap($rendered);
$html = ob_get_clean();
$same(true, str_contains($html, 'data-layout="acf/lazy-test-child"'), 'Editor layout choice is missing');
$same(true, str_contains($html, 'value="Hello"'), 'Editor nested value is missing');
$same(true, str_contains($html, 'value="p-4"'), 'Editor CSS class value is missing');

$eager = InnerBlocks::make('Eager', 'eager')->include(['acf/lazy-test-child'])->lazyLayouts(false);
$same(['acf/lazy-test-child'], array_column($eager->toArray('lazy_eager_test')['layouts'], 'name'), 'Eager export is unavailable');

$toggle = InnerBlocks::make('Toggle', 'toggle')->include(['acf/lazy-test-child']);
$toggle->toArray('lazy_toggle_test');
$toggle->exclude(['acf/lazy-test-child'])->lazyLayouts(false);
$toggleExport = $toggle->toArray('lazy_toggle_test');
acf_add_local_field($toggleExport);
$same([], acf_get_field($toggleExport['key'])['layouts'], 'An eager export retained an earlier lazy builder');

echo "Lazy InnerBlocks WordPress: $checks checks passed.\n";
