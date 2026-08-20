<?php

declare(strict_types=1);

namespace CloakWP\ACF\ConditionalChoices;

/**
 * Evaluates compiled conditional-choice rules against controller values.
 *
 * Kept deliberately isomorphic with the JS evaluator so client and server
 * enforce the same allow-list.
 */
final class RuleEvaluator
{
    /**
     * @param array{
     *   canonical_choices: list<string>,
     *   rules: array<string, list<list<array{field: string, name: string, operator: string, value?: mixed}>>>
     * } $compiled
     * @param array<string, mixed> $controllerValues keyed by field key
     * @return list<string>
     */
    public static function availableChoices(array $compiled, array $controllerValues): array
    {
        $canonical = $compiled['canonical_choices'] ?? [];
        $rules = $compiled['rules'] ?? [];
        $available = [];

        foreach ($canonical as $choice) {
            $choiceKey = (string) $choice;

            if (!isset($rules[$choiceKey])) {
                $available[] = $choiceKey;
                continue;
            }

            if (self::choiceMatches($rules[$choiceKey], $controllerValues)) {
                $available[] = $choiceKey;
            }
        }

        return $available;
    }

    /**
     * @param list<list<array{field: string, name: string, operator: string, value?: mixed}>> $orGroups
     * @param array<string, mixed> $controllerValues
     */
    public static function choiceMatches(array $orGroups, array $controllerValues): bool
    {
        foreach ($orGroups as $andGroup) {
            if (self::andGroupMatches($andGroup, $controllerValues)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{field: string, name: string, operator: string, value?: mixed}> $andGroup
     * @param array<string, mixed> $controllerValues
     */
    private static function andGroupMatches(array $andGroup, array $controllerValues): bool
    {
        foreach ($andGroup as $rule) {
            $fieldKey = $rule['field'];

            if (!array_key_exists($fieldKey, $controllerValues)) {
                return false;
            }

            if (!self::ruleMatches($rule, $controllerValues[$fieldKey])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{field: string, name: string, operator: string, value?: mixed} $rule
     */
    public static function ruleMatches(array $rule, mixed $actual): bool
    {
        $operator = $rule['operator'];
        $expected = $rule['value'] ?? null;

        $actualEmpty = self::isEmpty($actual);

        return match ($operator) {
            '==empty' => $actualEmpty,
            '!=empty' => !$actualEmpty,
            '==' => self::equals($actual, $expected),
            '!=' => !self::equals($actual, $expected),
            '>' => self::compareNumeric($actual, $expected) > 0,
            '<' => self::compareNumeric($actual, $expected) < 0,
            '==contains' => self::contains($actual, $expected),
            '==pattern' => self::matchesPattern($actual, $expected),
            default => false,
        };
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return true;
        }

        if (is_array($value)) {
            return $value === [];
        }

        return $value === '' || $value === [];
    }

    private static function equals(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if (self::equals($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        return (string) $actual === (string) $expected;
    }

    private static function compareNumeric(mixed $actual, mixed $expected): int
    {
        if (!is_numeric($actual) || !is_numeric($expected)) {
            return 0;
        }

        return (float) $actual <=> (float) $expected;
    }

    private static function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if (self::contains($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        return str_contains((string) $actual, (string) $expected);
    }

    private static function matchesPattern(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if (self::matchesPattern($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        $pattern = (string) $expected;
        $delimited = str_starts_with($pattern, '/') ? $pattern : '/' . str_replace('/', '\/', $pattern) . '/';

        return @preg_match($delimited, (string) $actual) === 1;
    }
}
