<?php

declare(strict_types=1);

// Run with WP-CLI eval-file --use-include; creates no posts or persistent settings.
use CloakWP\ACF\{Block, BlockFieldIndex, BlockRegistry};
use CloakWP\ACF\Fields\InnerBlocks;
use Extended\ACF\Fields\{Group, PostObject, Text};
use Extended\ACF\Key;

$checks = 0;
$same = static function ($expected, $actual, string $message) use (&$checks): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$folder = sys_get_temp_dir() . '/cloakwp-targeted-fields-' . uniqid();
mkdir($folder);
$file = $folder . '/block.json';
file_put_contents($file, wp_json_encode([
    'apiVersion' => 3, 'name' => 'acf/targeted-test-child', 'title' => 'Targeted Test Child',
    'acf' => ['mode' => 'edit', 'renderTemplate' => 'unused.php'],
]));
$skip = static fn(): bool => false;
try {
    $child = Block::make($file)->collapsible(false)
        ->fields([Group::make('Content')->fields([PostObject::make('Target')])])
        ->value(static fn(array $value): array => $value + ['callbackRan' => true]);
    add_filter('cloakwp/acf/block/register_field_group', $skip);
    $child->register();
    remove_filter('cloakwp/acf/block/register_field_group', $skip);
    $settings = $child->getFieldGroupSettings();
    $same(true, WP_Block_Type_Registry::get_instance()->is_registered('acf/targeted-test-child'), 'Block metadata was skipped');
    $same(true, apply_filters('cloakwp/block/name=acf/targeted-test-child', [])['callbackRan'], 'Child value callback was skipped');
    $same(false, (bool) acf_get_local_field_group('group_' . Key::hash(Key::sanitize($settings['key']))), 'Schema imported despite policy');

    $inner = InnerBlocks::make('Children')->include(['acf/targeted-test-child']);
    $owner = new class($inner) extends Block {
        public function __construct(private InnerBlocks $inner) {}
        public function getFieldGroupSettings(): array
        {
            return ['key' => 'block_targeted_test_owner', 'name' => 'acf/targeted-test-owner',
                'title' => 'Targeted test owner', 'fields' => [$this->inner],
                'location' => [\Extended\ACF\Location::where('block', 'acf/targeted-test-owner')]];
        }
    };
    $catalog = BlockRegistry::getInstance();
    $catalog->addBlock($owner)->addBlock($child);
    $same(1, count(array_filter($catalog->getBlocks(), static fn($b) => $b === $child)), 'Catalog duplicates a block');
    $key = 'field_' . Key::hash('block_targeted_test_owner_children_' . Key::sanitize('acf/targeted-test-child') . '_content_target');
    $match = (new BlockFieldIndex([$owner, $child]))->find($key, 'post_object');
    $same($owner, $match['block'] ?? null, 'Nested selector owner differs');
    $owner->registerFieldGroup();
    $same(false, (bool) acf_get_local_field($key), 'Lazy selector imported early');
    foreach ($match['layouts'] as $ancestor) {
        acf_get_field($ancestor);
    }
    $same('post_object', acf_get_local_field($key)['type'], 'Ancestor did not import selector');
    $count = count(acf_get_local_store('fields')->get());
    $owner->registerFieldGroup();
    $same($count, count(acf_get_local_store('fields')->get()), 'Explicit schema import repeated');

    if (class_exists(\Theme\App\Providers\BlocksProvider::class)) {
        $provider = \Theme\App\Providers\BlocksProvider::class;
        $selection = new ReflectionProperty($provider, 'selectorFields');
        $groups = new ReflectionProperty($provider, 'fieldGroupNames');
        $site = get_current_blog_id();
        $selection->setValue(null, [$site => ['key' => 'field_custom_export_mismatch',
            'type' => 'post_object', 'layouts' => []]]);
        $groups->setValue(null, [$site => 'acf/targeted-test-owner']);
        $provider::loadSelectorField();
        $directKey = 'field_' . Key::hash(Key::sanitize($settings['key']) . '_content_target');
        $same('post_object', acf_get_local_field($directKey)['type'], 'Mismatch did not restore unrelated schema');
        $same([], $groups->getValue(), 'Fallback retained selected-schema restriction');
        $provider::reset();
    }
    $ui = Block::make($file)->fieldsViaUI();
    $beforeUI = count(acf_get_local_store('fields')->get());
    $ui->registerFieldGroup();
    $same($beforeUI, count(acf_get_local_store('fields')->get()), 'UI-managed schema was imported');

    $repeat = InnerBlocks::make('Repeat')->include(['acf/targeted-test-child']);
    $repeat->toArray('targeted_test_repeat');
    $export = $repeat->toArray('targeted_test_repeat');
    $hook = $GLOBALS['wp_filter']['acf/format_value/key=' . $export['key']];
    $same(1, count($hook->callbacks[10]), 'Repeated export duplicated formatter');

    $retryChild = new class extends Block {
        public function __construct() {}
        public function getFieldGroupSettings(): array
        {
            return ['key' => 'block_targeted_retry', 'name' => 'acf/targeted-retry', 'title' => 'Retry',
                'fields' => [Text::make('Already exported', 'already_exported'), new class('Retry', 'retry') extends Text {
                    private static bool $failed = false;
                    public function toArray(?string $parentKey = null): array
                    {
                        if (!self::$failed) {
                            self::$failed = true;
                            throw new RuntimeException('Expected transient export failure');
                        }
                        return parent::toArray($parentKey);
                    }
                }]];
        }
    };
    $catalog->addBlock($retryChild);
    $retry = InnerBlocks::make('Retry')->include(['acf/targeted-retry'])->toArray('targeted_retry_owner');
    acf_add_local_field($retry);
    try {
        acf_get_field($retry['key']);
        throw new LogicException('Export unexpectedly succeeded');
    } catch (RuntimeException $error) {
        $same('Expected transient export failure', $error->getMessage(), 'Unexpected export error');
    }
    $same(['acf/targeted-retry'], array_column(acf_get_field($retry['key'])['layouts'], 'name'), 'Failed loader could not retry');

    $original = $catalog->getBlocks();
    $otherSites = get_sites(['fields' => 'ids', 'site__not_in' => [get_current_blog_id()], 'number' => 1]);
    if ($otherSites !== []) {
        switch_to_blog((int) $otherSites[0]);
        try {
            $same([], $catalog->getBlocks(), 'Another site inherited the catalog');
            $catalog->addBlock($retryChild);
            $same([$retryChild], $catalog->getBlocks(), 'Site catalog failed to populate');
        } finally {
            restore_current_blog();
        }
        $same($original, $catalog->getBlocks(), 'Restoring site lost its catalog');
    }
} finally {
    remove_filter('cloakwp/acf/block/register_field_group', $skip);
    WP_Block_Type_Registry::get_instance()->unregister('acf/targeted-test-child');
    unlink($file);
    rmdir($folder);
}
echo "Targeted block fields WordPress: $checks checks passed.\n";
