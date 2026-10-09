<?php

declare(strict_types=1);

namespace CloakWP\ACF\InnerBlocks;

use InvalidArgumentException;

/** Load native ACF clone controls only when a layout is first added. */
final class LayoutTemplates
{
    public const ACTION = 'cloakwp/acf/inner_blocks/layout';
    private const SCRIPT = 'cloakwp-acf-inner-blocks-layouts';
    private static bool $booted = false;
    private static int $renderingTemplates = 0;
    /** @var array<string, bool> Prepared input paths for forms rendered this request. */
    private static array $renderedFields = [];

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        add_filter('acf/prepare_field', [self::class, 'prepareField'], 20);
        add_filter('acf/field_wrapper_attributes', [self::class, 'wrapperAttributes'], 20, 2);
        add_action('wp_ajax_' . self::ACTION, [self::class, 'ajax']);
        add_action('acf/input/admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue']);
    }

    public static function enabled(array $field): bool
    {
        // Older ACF versions keep their ordinary forms and permission handling.
        return !empty($field['cloakwp_inner_blocks'])
            && !empty($field['cloakwp_lazy_templates'])
            && function_exists('acf_current_user_can_edit_in_context')
            && is_callable([acf_get_field_type('flexible_content'), 'render_layout'])
            && (bool) apply_filters('cloakwp/acf/inner_blocks/lazy_templates', true, $field);
    }

    public static function prepareField(array|false $field): array|false
    {
        if (!$field || self::$renderingTemplates > 0
            || !str_ends_with($field['prefix'] ?? '', '[acfcloneindex]')
        ) {
            return $field;
        }
        $parentPath = substr($field['prefix'], 0, -strlen('[acfcloneindex]'));
        return !empty(self::$renderedFields[$parentPath]) ? false : $field;
    }

    public static function wrapperAttributes(array $attributes, array $field): array
    {
        if (($field['type'] ?? '') === 'flexible_content') {
            $enabled = self::enabled($field) && self::canLoadTemplates();
            self::$renderedFields[$field['name']] = $enabled;
            if ($enabled) {
                $attributes['data-cloakwp-lazy-layouts'] = '1';
            }
        }
        return $attributes;
    }

    private static function canLoadTemplates(): bool
    {
        // Public forms and unsupported edit contexts retain their eager controls.
        $postId = acf_get_form_data('post_id') ?: get_the_ID();
        if (!$postId) {
            return false;
        }
        $page = is_string($_GET['page'] ?? null) ? wp_unslash($_GET['page']) : '';
        return acf_current_user_can_edit_in_context(acf_decode_post_id($postId), $page);
    }

    public static function enqueue(): void
    {
        $root = dirname(__DIR__, 2);
        wp_enqueue_script(self::SCRIPT,
            plugins_url('js/inner-blocks-layouts.js', $root . '/cloakwp-acf-abstractions.php'),
            ['jquery', 'acf-pro-input'], (string) filemtime($root . '/js/inner-blocks-layouts.js'), true);
    }

    /** @internal Render the native template with its actual input path. */
    public static function renderTemplate(array $field, string $name, string $inputName): string
    {
        if (strlen($inputName) > 2048
            || !preg_match('/\Aacf(?:-[a-zA-Z0-9_-]+)?(?:\[[a-zA-Z0-9_-]+\])+\z/', $inputName)
            || !str_ends_with($inputName, '[' . ($field['key'] ?? '') . ']')
        ) {
            throw new InvalidArgumentException('Invalid field input name.');
        }
        $layout = null;
        foreach ($field['layouts'] ?? [] as $candidate) {
            if ($candidate['name'] === $name) {
                $layout = $candidate;
                break;
            }
        }
        if ($layout === null || empty($field['cloakwp_inner_blocks'])) {
            throw new InvalidArgumentException('Unknown block layout.');
        }
        $field['name'] = $inputName;
        self::$renderingTemplates++;
        ob_start();
        try {
            acf_get_field_type('flexible_content')->render_layout($field, $layout, 'acfcloneindex', []);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            self::$renderingTemplates--;
        }
    }

    public static function ajax(): void
    {
        if (!function_exists('acf_current_user_can_edit_in_context')) {
            wp_send_json_error(['message' => 'Block templates are unavailable.'], 404);
        }
        $request = wp_unslash($_POST);
        foreach (['field_key', 'nonce', 'layout', 'input_name', 'post_id'] as $key) {
            if (!isset($request[$key]) || !is_scalar($request[$key])) {
                wp_send_json_error(['message' => 'Invalid layout request.'], 400);
            }
        }
        $key = (string) $request['field_key'];
        if (!preg_match('/\Afield_[a-zA-Z0-9_-]{1,128}\z/', $key)) {
            wp_send_json_error(['message' => 'Could not verify the field request.'], 403);
        }
        $page = is_string($request['options_page'] ?? null) ? $request['options_page'] : '';
        $context = acf_decode_post_id($request['post_id']);
        if (!acf_current_user_can_edit_in_context($context, $page)) {
            wp_send_json_error(['message' => 'You do not have permission to edit these fields.'], 403);
        }
        if ($context['type'] === 'post') {
            // Match ACF's block form context for filters and field defaults.
            $GLOBALS['post'] = get_post($context['id']);
            if ($GLOBALS['post']) {
                setup_postdata($GLOBALS['post']);
            }
        }
        if (!acf_verify_ajax((string) $request['nonce'], $key, true, 'flexible_content')) {
            wp_send_json_error(['message' => 'Could not verify the field request.'], 403);
        }
        $field = acf_get_field($key);
        if (!$field || !self::enabled($field)) {
            wp_send_json_error(['message' => 'This field does not support block templates.'], 404);
        }
        try {
            $html = self::renderTemplate($field, (string) $request['layout'], (string) $request['input_name']);
        } catch (InvalidArgumentException $error) {
            wp_send_json_error(['message' => $error->getMessage()], 400);
        }
        wp_send_json_success(['html' => $html]);
    }
}
