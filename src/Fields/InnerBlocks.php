<?php

declare(strict_types=1);

namespace CloakWP\ACF\Fields;

use CloakWP\ACF\BlockRegistry;
use CloakWP\ACF\InnerBlocks\LayoutTemplates;
use Extended\ACF\Fields\{Accordion, Field, FlexibleContent, Layout, Text};

/** Flexible-content layouts built from the current site's block catalog. */
class InnerBlocks extends FlexibleContent
{
    /** @var array<int, array<string, callable|null>> */
    private static array $pendingLayouts = [];
    /** @var array<int, array<string, true>> */
    private static array $buildingLayouts = [];
    private static bool $layoutLoaderRegistered = false;
    private bool $lazyLayouts = true;
    private bool $lazyTemplates = true;
    private array $includes = [];
    private array $excludes = [];
    public bool $excludeFromInnerBlocks = true;

    public static function make(string $label, ?string $name = null): static
    {
        return (new static($label, $name))->button('Add block');
    }

    public function include(array $includedBlocks): static
    {
        $this->includes = $includedBlocks;
        return $this;
    }

    public function exclude(array $excludedBlocks): static
    {
        $this->excludes = $excludedBlocks;
        return $this;
    }

    public function lazyLayouts(bool $lazy = true): static
    {
        $this->lazyLayouts = $lazy;
        return $this;
    }

    /** Keep native eager clone controls for integrations that need them. */
    public function lazyTemplates(bool $lazy = true): static
    {
        $this->lazyTemplates = $lazy;
        return $this;
    }

    private static function registerLayoutLoader(): void
    {
        if (self::$layoutLoaderRegistered) {
            return;
        }
        self::$layoutLoaderRegistered = true;
        add_filter('cloakwp/block_parser/value_aware_fields', '__return_true', 0);
        add_filter('acf/load_field/type=flexible_content', [self::class, 'loadLayouts'], 5);
    }

    /** @internal Expand before ACF resolves the flexible-content children. */
    public static function loadLayouts(array $field): array
    {
        $site = get_current_blog_id();
        $key = $field['key'];
        if (!array_key_exists($key, self::$pendingLayouts[$site] ?? [])) {
            return $field;
        }
        if (!apply_filters('cloakwp/acf/inner_blocks/load_layouts', true, $field)) {
            $field['layouts'] = [];
            return $field;
        }
        $required = apply_filters('cloakwp/acf/inner_blocks/required_layouts', null, $field);
        $build = self::$pendingLayouts[$site][$key];
        if ($build === null || isset(self::$buildingLayouts[$site][$key])) {
            $field['layouts'] = self::selectLayouts($field['layouts'], $required);
            return $field;
        }
        self::$buildingLayouts[$site][$key] = true;
        try {
            $layouts = $build($required)['layouts'];
            $local = acf_get_local_field($key);
            $existing = array_column($local['layouts'] ?? [], null, 'name');
            $import = array_column($layouts, null, 'name');
            if ($required === null || array_diff_key($import, $existing) !== []) {
                // Keep earlier imports available for another occurrence in this request.
                $local['layouts'] = $required === null ? $layouts : array_values($existing + $import);
                acf_add_local_field($local);
            }
            $field['layouts'] = self::selectLayouts(acf_get_local_field($key)['layouts'], $required);
            // Partial views must leave the builder available for other layouts and
            // ordinary ACF reads. Failed exports remain available for retry too.
            if ($required === null) {
                self::$pendingLayouts[$site][$key] = null;
            }
        } finally {
            unset(self::$buildingLayouts[$site][$key]);
        }
        return $field;
    }

    /** @param list<string>|null $required */
    private static function selectLayouts(array $layouts, ?array $required): array
    {
        return $required === null ? $layouts : array_values(array_filter($layouts,
            static fn(array $layout): bool => in_array($layout['name'], $required, true)));
    }

    /** Return eligible layout builders without exporting or importing fields. */
    public function getLayouts(?string $parentKey): array
    {
        $layouts = [];
        foreach (BlockRegistry::getInstance()->getBlocks() as $block) {
            $settings = $block->getFieldGroupSettings();
            $name = $settings['name'];
            if (($this->includes !== [] && !in_array($name, $this->includes, true))
                || in_array($name, $this->excludes, true)
                || str_starts_with($parentKey ?? '', $settings['key'])
            ) {
                continue;
            }
            $layouts[] = Layout::make($settings['title'], $name)
                ->layout('block')
                ->fields([...self::filterNestedFields($settings['fields']), Text::make('CSS Class(es)', 'className')]);
        }
        return $layouts;
    }

    private function exportLayouts(?string $parentKey, array $layouts): array
    {
        $hadLayouts = array_key_exists('layouts', $this->settings);
        $previousLayouts = $this->settings['layouts'] ?? null;
        $this->settings['layouts'] = $layouts;
        try {
            return parent::toArray($parentKey);
        } finally {
            if ($hadLayouts) {
                $this->settings['layouts'] = $previousLayouts;
            } else {
                unset($this->settings['layouts']);
            }
        }
    }

    /** @internal */
    public function toArray(?string $parentKey = null): array
    {
        $localFields = function_exists('acf_add_local_field');
        $lazy = $this->lazyLayouts && $localFields;
        $result = $this->exportLayouts($parentKey, $lazy ? [] : $this->getLayouts($parentKey));
        $result['cloakwp_inner_blocks'] = true;
        $result['cloakwp_lazy_layouts'] = $lazy;
        $result['cloakwp_lazy_templates'] = $this->lazyTemplates;
        if ($localFields) {
            LayoutTemplates::boot();
        }
        if ($lazy) {
            $builder = clone $this;
            self::$pendingLayouts[get_current_blog_id()][$result['key']] = static function (?array $required = null) use ($builder, $parentKey): array {
                // Keep partially exported children on retry: Extended ACF records
                // generated keys on each builder as it exports them.
                static $layouts = null;
                $layouts ??= $builder->getLayouts($parentKey);
                $selected = $required === null ? $layouts : array_filter($layouts,
                    static fn(Layout $layout): bool => in_array($layout->settings['name'], $required, true));
                return $builder->exportLayouts($parentKey, array_values($selected));
            };
            self::registerLayoutLoader();
        } elseif ($localFields) {
            unset(self::$pendingLayouts[get_current_blog_id()][$result['key']]);
        }
        // WordPress deduplicates this named callback on repeated exports.
        add_filter("acf/format_value/key={$result['key']}", [self::class, 'formatValue'], 10, 3);
        return $result;
    }

    /** @internal Format rows as the parser's ordinary structured blocks. */
    public static function formatValue($value, $postId, $field)
    {
        if (!is_array($value) || $value === []) {
            return $value;
        }
        $actualPostId = self::resolvePostId($postId);
        $blocks = [];
        foreach ($value as $layout) {
            $name = $layout['acf_fc_layout'];
            unset($layout['acf_fc_layout']);
            $blocks[] = apply_filters('cloakwp/block', [
                'name' => $name, 'type' => 'acf', 'attrs' => [], 'data' => $layout,
            ], ['name' => $name, 'type' => 'acf'], $actualPostId);
        }
        return $blocks;
    }

    private static function resolvePostId($postId)
    {
        $actualPostId = null;
        if ($postId) {
            $post = get_post($postId);
            if ($post && $post->post_type !== 'wp_block') {
                $actualPostId = $postId;
            } else {
                $actualPostId = get_the_ID();
                if (!$actualPostId && is_object($GLOBALS['wp_query'] ?? null)) {
                    $actualPostId = $GLOBALS['wp_query']->get_queried_object_id();
                }
            }
        }
        return $actualPostId ?: ($GLOBALS['post']->ID ?? null);
    }

    /** Clone compatible children without changing the source builders. */
    private static function filterNestedFields(array $fields): array
    {
        $filtered = [];
        $position = 0;
        foreach ($fields as $index => $field) {
            $isWrapper = $position === 0 || $position === count($fields) - 1;
            $position++;
            if (!$field instanceof Field || ($field->excludeFromInnerBlocks ?? false)
                || ($isWrapper && get_class($field) === Accordion::class)
            ) {
                continue;
            }
            $copy = clone $field;
            foreach (['sub_fields', 'layouts'] as $children) {
                if (isset($copy->settings[$children])) {
                    $copy->settings[$children] = self::filterNestedFields($copy->settings[$children]);
                }
            }
            $filtered[$index] = $copy;
        }
        return $filtered;
    }
}
