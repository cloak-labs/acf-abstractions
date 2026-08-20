<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\ConditionalChoices;

use CloakWP\ACF\ConditionalChoices\RuleEvaluator;
use PHPUnit\Framework\TestCase;

final class RuleEvaluatorTest extends TestCase
{
    public function testUnlistedChoicesRemainAvailable(): void
    {
        $compiled = [
            'canonical_choices' => ['auto', 'full', 'stretch'],
            'rules' => [
                'stretch' => [[
                    ['field' => 'field_hero_style', 'name' => 'hero_style', 'operator' => '==', 'value' => 'bg_image'],
                ]],
            ],
        ];

        $available = RuleEvaluator::availableChoices($compiled, [
            'field_hero_style' => 'image_right',
        ]);

        $this->assertSame(['auto', 'full'], $available);
    }

    public function testMatchingRuleUnlocksChoice(): void
    {
        $compiled = [
            'canonical_choices' => ['auto', 'full', 'stretch'],
            'rules' => [
                'stretch' => [[
                    ['field' => 'field_hero_style', 'name' => 'hero_style', 'operator' => '==', 'value' => 'bg_image'],
                ]],
            ],
        ];

        $available = RuleEvaluator::availableChoices($compiled, [
            'field_hero_style' => 'bg_image',
        ]);

        $this->assertSame(['auto', 'full', 'stretch'], $available);
    }

    public function testFalsyExpectedValuesAreHonored(): void
    {
        $this->assertTrue(RuleEvaluator::ruleMatches(
            ['field' => 'field_a', 'name' => 'a', 'operator' => '==', 'value' => '0'],
            '0',
        ));

        $this->assertTrue(RuleEvaluator::ruleMatches(
            ['field' => 'field_a', 'name' => 'a', 'operator' => '==', 'value' => 0],
            0,
        ));
    }

    public function testOrGroupsAndAndGroups(): void
    {
        $orGroups = [
            [
                ['field' => 'field_a', 'name' => 'a', 'operator' => '==', 'value' => '1'],
                ['field' => 'field_b', 'name' => 'b', 'operator' => '==', 'value' => '2'],
            ],
            [
                ['field' => 'field_a', 'name' => 'a', 'operator' => '==', 'value' => '9'],
            ],
        ];

        $this->assertFalse(RuleEvaluator::choiceMatches($orGroups, [
            'field_a' => '1',
            'field_b' => '0',
        ]));

        $this->assertTrue(RuleEvaluator::choiceMatches($orGroups, [
            'field_a' => '1',
            'field_b' => '2',
        ]));

        $this->assertTrue(RuleEvaluator::choiceMatches($orGroups, [
            'field_a' => '9',
            'field_b' => '0',
        ]));
    }

    public function testOperators(): void
    {
        $this->assertTrue(RuleEvaluator::ruleMatches(
            ['field' => 'f', 'name' => 'f', 'operator' => '==empty'],
            '',
        ));
        $this->assertTrue(RuleEvaluator::ruleMatches(
            ['field' => 'f', 'name' => 'f', 'operator' => '!=empty'],
            'x',
        ));
        $this->assertTrue(RuleEvaluator::ruleMatches(
            ['field' => 'f', 'name' => 'f', 'operator' => '>', 'value' => 1],
            2,
        ));
        $this->assertTrue(RuleEvaluator::ruleMatches(
            ['field' => 'f', 'name' => 'f', 'operator' => '<', 'value' => 5],
            2,
        ));
        $this->assertTrue(RuleEvaluator::ruleMatches(
            ['field' => 'f', 'name' => 'f', 'operator' => '==contains', 'value' => 'ell'],
            'hello',
        ));
        $this->assertTrue(RuleEvaluator::ruleMatches(
            ['field' => 'f', 'name' => 'f', 'operator' => '==pattern', 'value' => '/^bg_/'],
            'bg_image',
        ));
    }
}
