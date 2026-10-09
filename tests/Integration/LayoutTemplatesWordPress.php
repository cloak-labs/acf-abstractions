<?php

declare(strict_types=1);

// wp eval-file tests/Integration/LayoutTemplatesWordPress.php --use-include --url=<site>

use CloakWP\ACF\{Block, BlockRegistry};
use CloakWP\ACF\Fields\InnerBlocks;
use CloakWP\ACF\InnerBlocks\LayoutTemplates;
use Extended\ACF\Fields\{Group, Repeater, Text};
use Extended\ACF\Location;

if (!function_exists('acf_current_user_can_edit_in_context')) {
    throw new RuntimeException('Run with ACF Pro 6.7.2+ active.');
}

$admin = get_user_by('login', get_super_admins()[0]);
$postIds = get_posts(['post_type' => 'page', 'numberposts' => 1, 'fields' => 'ids']);
if (!$admin || !$postIds) {
    throw new RuntimeException('The checks need an administrator and an existing page.');
}
$originalUser = get_current_user_id();
$originalFormPost = acf_get_form_data('post_id');
wp_set_current_user($admin->ID);
acf_set_form_data('post_id', $postIds[0]);

$checks = 0;
$same = static function ($expected, $actual, string $message) use (&$checks): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$title = Text::make('Title', 'title')->default('Default title');
$nested = Group::make('Content', 'content')->fields([
    $title, Repeater::make('Items', 'items')->fields([Text::make('Item', 'item')]),
]);
BlockRegistry::getInstance()->addBlock(new class($nested) extends Block {
    public function __construct(private Group $nested) {}
    public function getFieldGroupSettings(): array
    {
        return ['name' => 'acf/template-test', 'key' => 'block_template_test',
            'title' => 'Template test', 'fields' => [$this->nested]];
    }
});
$settings = register_extended_field_group([
    'title' => 'Template test', 'key' => 'template_test',
    'location' => [Location::where('post_type', 'page')],
    'fields' => [InnerBlocks::make('Blocks', 'blocks')->include(['acf/template-test'])],
]);
$field = acf_get_field($settings['fields'][0]['key']);
$same(true, LayoutTemplates::enabled($field), 'Native template support unavailable');
$same(['acf/template-test'], array_column($field['layouts'], 'name'), 'Layout choices changed');
$group = $field['layouts'][0]['sub_fields'][0];
$child = $group['sub_fields'][0];
$row = ['acf_fc_layout' => 'acf/template-test', $group['key'] => [$child['key'] => 'Saved title']];
$field['prefix'] = 'acf-test[parent][row-0]';
$field['value'] = [$row];
$render = static function (array $field): string {
    ob_start();
    acf_render_field_wrap($field);
    return (string) ob_get_clean();
};
$parse = static function (string $html): DOMXPath {
    $doc = new DOMDocument();
    $before = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($before);
    return new DOMXPath($doc);
};
$path = $field['prefix'] . '[' . $field['key'] . ']';
$lazy = $parse($render($field));
$same(0, $lazy->query('//div[@class="clones"]//div[contains(concat(" ", @class, " "), " acf-field ")]')->length,
    'Hidden clone controls still rendered');
$same(1, $lazy->query('//input[@value="Saved title"]')->length, 'Existing value did not render');
$same(1, $lazy->query('//*[@data-cloakwp-lazy-layouts="1"]')->length, 'Lazy field marker missing');
$same(false, LayoutTemplates::prepareField(array_replace($group, ['prefix' => $path . '[acfcloneindex]'])), 'Clone children not deferred');
$same(array_replace($group, ['prefix' => 'acf[acfcloneindex][row-0]']),
    LayoutTemplates::prepareField(array_replace($group, ['prefix' => 'acf[acfcloneindex][row-0]'])), 'Saved row under a parent clone was suppressed');
$same(false, LayoutTemplates::prepareField(false), 'Earlier prepare filter was not respected');

$template = $parse(LayoutTemplates::renderTemplate($field, 'acf/template-test', $path));
$same(1, $template->query('//input[@value="Default title"]')->length, 'Template default was lost');
$same(0, $template->query('//input[@value="Saved title"]')->length, 'Template contained existing values');
$same(true, $template->query('//input[contains(@name, "[acfcloneindex]")]')->length > 0, 'Native clone names missing');
$same(true, $template->query('//div[contains(concat(" ", @class, " "), " acf-field-repeater ")]')->length > 0, 'Nested controls missing');

$eagerField = $field;
$eagerField['cloakwp_lazy_templates'] = false;
$eager = $parse($render($eagerField));
$same(0, $eager->query('//*[@data-cloakwp-lazy-layouts="1"]')->length, 'Eager field was intercepted');
$eagerClone = $eager->query('//div[@class="clones"]/div[@data-layout="acf/template-test"]')->item(0);
$fetchedClone = $template->query('//div[@data-layout="acf/template-test"]')->item(0);
$same($eagerClone->C14N(), $fetchedClone->C14N(), 'Fetched controls differ from native eager controls');

foreach (['acf[wrong_field]', 'acf[bad<name>][' . $field['key'] . ']', 'wrong[' . $field['key'] . ']'] as $invalid) {
    try {
        LayoutTemplates::renderTemplate($field, 'acf/template-test', $invalid);
        throw new RuntimeException('Invalid input path accepted');
    } catch (InvalidArgumentException) {
        $checks++;
    }
}
try {
    LayoutTemplates::renderTemplate($field, 'acf/not-in-this-field', $path);
    throw new RuntimeException('Foreign layout accepted');
} catch (InvalidArgumentException) {
    $checks++;
}
wp_set_current_user(0);
$publicForm = $parse($render($field));
$same(0, $publicForm->query('//*[@data-cloakwp-lazy-layouts="1"]')->length, 'Public form lost its eager fallback');
$same(true, $publicForm->query('//div[@class="clones"]//input')->length > 0, 'Public form controls were deferred');
wp_set_current_user($admin->ID);
$render($field); // Restore this form's lazy rendering policy after the eager comparison.
$buffer = ob_get_level();
$throw = static function ($candidate) { throw new RuntimeException('Intentional rendering failure'); };
add_filter('acf/prepare_field', $throw, 30);
try {
    LayoutTemplates::renderTemplate($field, 'acf/template-test', $path);
} catch (RuntimeException $error) {
    $same('Intentional rendering failure', $error->getMessage(), 'Unexpected rendering error');
} finally {
    remove_filter('acf/prepare_field', $throw, 30);
}
$same($buffer, ob_get_level(), 'Rendering failure leaked an output buffer');
$same(false, LayoutTemplates::prepareField(array_replace($group, ['prefix' => $path . '[acfcloneindex]'])), 'Rendering failure leaked suppression state');
$same(true, str_contains(LayoutTemplates::renderTemplate($field, 'acf/template-test', $path), 'Default title'), 'Rendering retry failed');
$same('Saved title', acf_format_value([$row], 0, $field)[0]['data']['content']['title'], 'Formatting changed');
$same([], acf_get_field($field['key'])['value'] ?? [], 'Rendering mutated the stored definition');
$disable = static fn () => false;
add_filter('cloakwp/acf/inner_blocks/lazy_templates', $disable);
$same(false, LayoutTemplates::enabled($field), 'Integration opt-out was ignored');
remove_filter('cloakwp/acf/inner_blocks/lazy_templates', $disable);

// Exercise the real authenticated handler without a network dependency or DB writes.
if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', true);
}
final class LayoutTemplateResponseEnd extends RuntimeException {}
$die = static fn () => static function () { throw new LayoutTemplateResponseEnd(); };
$previousUser = get_current_user_id();
wp_set_current_user($admin->ID);
$request = [
    'field_key' => $field['key'],
    'nonce' => wp_create_nonce('acf_field_flexible_content_' . $field['key']),
    'layout' => 'acf/template-test', 'input_name' => $path, 'post_id' => $postIds[0],
];
$previousPost = $GLOBALS['post'] ?? null;
$previousInput = $_POST;
add_filter('wp_die_ajax_handler', $die);
try {
    foreach ([
        [[], true, 'Native AJAX request failed'],
        [['nonce' => 'invalid'], false, 'Invalid nonce accepted'],
        [['layout' => 'acf/foreign-layout'], false, 'Foreign AJAX layout accepted'],
        [['input_name' => 'acf[field_other]'], false, 'Unrelated AJAX input path accepted'],
        [['post_id' => 999999999], false, 'Nonexistent edit context accepted'],
        [['layout' => ['acf/template-test']], false, 'Array input accepted'],
    ] as [$overrides, $success, $message]) {
        $_POST = array_replace($request, $overrides);
        ob_start();
        try {
            LayoutTemplates::ajax();
            throw new RuntimeException('Handler did not send a response');
        } catch (LayoutTemplateResponseEnd) {
            $response = json_decode((string) ob_get_contents(), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
        $same($success, $response['success'], $message);
    }
    wp_set_current_user(0);
    ob_start();
    try {
        $_POST = $request;
        LayoutTemplates::ajax();
    } catch (LayoutTemplateResponseEnd) {
        $response = json_decode((string) ob_get_contents(), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        ob_end_clean();
    }
    $same(false, $response['success'], 'Unauthenticated edit context accepted');
} finally {
    remove_filter('wp_die_ajax_handler', $die);
    $_POST = $previousInput;
    $GLOBALS['post'] = $previousPost;
    wp_reset_postdata();
    wp_set_current_user($previousUser);
}

acf_set_form_data('post_id', $originalFormPost);
wp_set_current_user($originalUser);

echo "InnerBlocks layout templates WordPress: $checks checks passed.\n";
