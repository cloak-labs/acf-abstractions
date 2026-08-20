<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\ConditionalChoices;

use CloakWP\ACF\ConditionalChoices\Bootstrap;
use CloakWP\ACF\ConditionalChoices\DefinitionCompiler;
use CloakWP\ACF\ConditionalChoices\DefinitionRegistry;
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\RadioButton;
use Extended\ACF\Key;
use PHPUnit\Framework\TestCase;

final class DefinitionCompilerTest extends TestCase
{
    protected function setUp(): void
    {
        DefinitionRegistry::reset();
        Bootstrap::registerMacro();

        $keys = new \ReflectionProperty(Key::class, 'keys');
        $keys->setValue(null, []);
    }

    public function testMacroStoresDefinitionIdAndCompilesStableKeys(): void
    {
        $field = RadioButton::make('Height')
            ->choices([
                'auto' => 'Auto',
                'full' => 'Full',
                'stretch' => 'Stretch',
            ])
            ->conditionalChoices([
                'stretch' => [
                    ConditionalLogic::where('hero_style', '==', 'bg_image'),
                ],
            ]);

        $parentKey = 'group_hero_hash_fallback';
        $array = $field->toArray($parentKey);

        $this->assertArrayHasKey('cloakwp_conditional_choices_id', $array);
        $this->assertSame('radio', $array['type']);

        $controllerLogical = $parentKey . '_hero_style';
        $expectedControllerKey = 'field_' . Key::hash($controllerLogical);

        $compiled = DefinitionCompiler::compile(
            DefinitionRegistry::require($array['cloakwp_conditional_choices_id']),
            [
                'key' => $array['key'],
                'name' => $array['name'],
                'type' => $array['type'],
                'parent' => $parentKey,
                'choices' => $array['choices'],
            ],
        );

        $this->assertSame(['auto', 'full', 'stretch'], $compiled['canonical_choices']);
        $this->assertSame($expectedControllerKey, $compiled['rules']['stretch'][0][0]['field']);
        $this->assertSame('bg_image', $compiled['rules']['stretch'][0][0]['value']);
        $this->assertSame('hero_style', $compiled['rules']['stretch'][0][0]['name']);
    }

    public function testFalsyRuleValuesArePreserved(): void
    {
        $group = ConditionalLogic::where('enabled', '==', '0');
        $compiled = DefinitionCompiler::compile(
            ['off' => [$group]],
            [
                'key' => 'field_target',
                'name' => 'target',
                'type' => 'select',
                'parent' => 'group_test',
                'choices' => ['on' => 'On', 'off' => 'Off'],
            ],
        );

        $this->assertSame('0', $compiled['rules']['off'][0][0]['value']);
    }

    public function testCompilePrefersLiveSiblingFieldKeyOverParentHash(): void
    {
        if (!function_exists('acf_get_local_fields')) {
            // Lightweight stub for unit tests outside WordPress.
            eval(<<<'PHP'
                function acf_get_local_fields($parent) {
                    if ($parent !== 'group_hero_sibling_lookup') {
                        return [];
                    }
                    return [
                        ['name' => 'hero_style', 'key' => 'field_live_hero_style'],
                        ['name' => 'height', 'key' => 'field_live_height'],
                    ];
                }
            PHP);
        }

        $compiled = DefinitionCompiler::compile(
            [
                'stretch' => [
                    ConditionalLogic::where('hero_style', '==', 'bg_image'),
                ],
            ],
            [
                'key' => 'field_live_height',
                'name' => 'height',
                'type' => 'radio',
                'parent' => 'group_hero_sibling_lookup',
                'choices' => [
                    'auto' => 'Auto',
                    'stretch' => 'Stretch',
                ],
            ],
        );

        $this->assertSame('field_live_hero_style', $compiled['rules']['stretch'][0][0]['field']);
        $this->assertSame('hero_style', $compiled['rules']['stretch'][0][0]['name']);
    }
}
