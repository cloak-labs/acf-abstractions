<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\ConditionalChoices;

use CloakWP\ACF\ConditionalChoices\SubmittedValueResolver;
use CloakWP\ACF\ConditionalChoices\Validator;
use PHPUnit\Framework\TestCase;

final class SubmittedValueResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testReplaceTerminalKeyPreservesNesting(): void
    {
        $input = 'acf[field_block][field_group][field_height]';
        $resolved = SubmittedValueResolver::replaceTerminalKey($input, 'field_hero_style');

        $this->assertSame('acf[field_block][field_group][field_hero_style]', $resolved);
    }

    public function testReadsNestedPostedValues(): void
    {
        $_POST = [
            'acf' => [
                'field_block' => [
                    'field_group' => [
                        'field_hero_style' => 'bg_image',
                    ],
                ],
            ],
        ];

        $value = SubmittedValueResolver::readFromRequest('acf[field_block][field_group][field_hero_style]');
        $this->assertSame('bg_image', $value);
    }

    public function testValidatorRejectsUnavailableChoice(): void
    {
        $_POST = [
            'acf' => [
                'field_hero_style' => 'image_right',
                'field_height' => 'stretch',
            ],
        ];

        $field = [
            'cloakwp_conditional_choices' => [
                'canonical_choices' => ['auto', 'full', 'stretch'],
                'rules' => [
                    'stretch' => [[
                        ['field' => 'field_hero_style', 'name' => 'hero_style', 'operator' => '==', 'value' => 'bg_image'],
                    ]],
                ],
            ],
        ];

        $result = Validator::validate(true, 'stretch', $field, 'acf[field_height]');
        $this->assertIsString($result);
        $this->assertStringContainsString('not available', $result);
    }

    public function testValidatorAcceptsAvailableChoice(): void
    {
        $_POST = [
            'acf' => [
                'field_hero_style' => 'bg_image',
                'field_height' => 'stretch',
            ],
        ];

        $field = [
            'cloakwp_conditional_choices' => [
                'canonical_choices' => ['auto', 'full', 'stretch'],
                'rules' => [
                    'stretch' => [[
                        ['field' => 'field_hero_style', 'name' => 'hero_style', 'operator' => '==', 'value' => 'bg_image'],
                    ]],
                ],
            ],
        ];

        $result = Validator::validate(true, 'stretch', $field, 'acf[field_height]');
        $this->assertTrue($result);
    }

    public function testValidatorAllowsUnconditionalChoicesWithoutControllers(): void
    {
        $_POST = [];

        $field = [
            'cloakwp_conditional_choices' => [
                'canonical_choices' => ['auto', 'full', 'stretch'],
                'rules' => [
                    'stretch' => [[
                        ['field' => 'field_hero_style', 'name' => 'hero_style', 'operator' => '==', 'value' => 'bg_image'],
                    ]],
                ],
            ],
        ];

        // Gutenberg block validation often can't resolve siblings via $_POST.
        // Always-available choices should not fail closed when controllers are absent.
        $result = Validator::validate(true, 'auto', $field, 'acf-block_abc123[field_height]');
        $this->assertTrue($result);
    }

    public function testExtractBlockIdFromAcfBlockInputName(): void
    {
        $this->assertSame(
            'block_abc123',
            SubmittedValueResolver::extractBlockId('acf-block_abc123[field_height]'),
        );
        $this->assertSame(
            'block_abc123',
            SubmittedValueResolver::extractBlockId('acf-block_abc123[field_group][field_height]'),
        );
        $this->assertNull(SubmittedValueResolver::extractBlockId('acf[field_height]'));
    }

    public function testResolveControllersFallsBackToLiveKeyByName(): void
    {
        if (!function_exists('acf_get_field')) {
            eval(<<<'PHP'
                function acf_get_field($selector) {
                    if ($selector === 'hero_style') {
                        return ['key' => 'field_live_hero_style', 'name' => 'hero_style'];
                    }
                    return false;
                }
            PHP);
        }

        $_POST = [
            'acf' => [
                // Posted under the live key; compiled rule still uses a stale hash key.
                'field_live_hero_style' => 'bg_image',
                'field_stale_height' => 'stretch',
            ],
        ];

        $values = SubmittedValueResolver::resolveControllers(
            'acf[field_stale_height]',
            ['field_stale_hero_style'],
            ['field_stale_hero_style' => 'hero_style'],
        );

        $this->assertSame('bg_image', $values['field_stale_hero_style']);

        $field = [
            'cloakwp_conditional_choices' => [
                'canonical_choices' => ['auto', 'stretch'],
                'rules' => [
                    'stretch' => [[
                        [
                            'field' => 'field_stale_hero_style',
                            'name' => 'hero_style',
                            'operator' => '==',
                            'value' => 'bg_image',
                        ],
                    ]],
                ],
            ],
        ];

        $this->assertTrue(
            Validator::validate(true, 'stretch', $field, 'acf[field_stale_height]'),
        );
    }
}
