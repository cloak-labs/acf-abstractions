<?php

declare(strict_types=1);

namespace CloakWP\ACF\ConditionalChoices;

use Extended\ACF\ConditionalLogic;
use Extended\ACF\Key;

/**
 * Compiles declarative ConditionalLogic groups into primitive JS/PHP payloads.
 */
final class DefinitionCompiler
{
    /**
     * @param array<string, list<ConditionalLogic>> $definition
     * @param array<string, mixed> $field
     * @return array{
     *   field_key: string,
     *   field_name: string,
     *   field_type: string,
     *   canonical_choices: list<string>,
     *   rules: array<string, list<list<array{field: string, name: string, operator: string, value?: mixed}>>>
     * }
     */
    public static function compile(array $definition, array $field): array
    {
        $parentKey = isset($field['parent']) && is_string($field['parent']) ? $field['parent'] : null;
        $canonical = array_map('strval', array_keys($field['choices'] ?? []));

        $rules = [];

        foreach ($definition as $choiceValue => $groups) {
            $choiceKey = (string) $choiceValue;

            if (!in_array($choiceKey, $canonical, true)) {
                throw new \InvalidArgumentException(
                    "conditionalChoices() references unknown choice [{$choiceKey}] on field [{$field['name']}].",
                );
            }

            $compiledGroups = [];

            foreach ($groups as $group) {
                $compiledGroups[] = self::compileGroup($group, $parentKey);
            }

            $rules[$choiceKey] = $compiledGroups;
        }

        return [
            'field_key' => (string) ($field['key'] ?? ''),
            'field_name' => (string) ($field['name'] ?? ''),
            'field_type' => (string) ($field['type'] ?? ''),
            'canonical_choices' => $canonical,
            'rules' => $rules,
        ];
    }

    /**
     * @return list<array{field: string, name: string, operator: string, value?: mixed}>
     */
    private static function compileGroup(ConditionalLogic $group, ?string $parentKey): array
    {
        $compiled = [];

        foreach ($group->rules as $rule) {
            $name = is_array($rule['name']) ? (string) reset($rule['name']) : (string) $rule['name'];
            $ruleParent = !empty($rule['group']) && is_string($rule['group']) ? $rule['group'] : $parentKey;

            // Prefer explicit key; then the live sibling field key; then Extended ACF's hash.
            // Support both `key` (where()) and `field` (and() bug/legacy) keys.
            $fieldKey = $rule['key'] ?? $rule['field'] ?? null;
            if (!is_string($fieldKey) || $fieldKey === '' || !str_starts_with($fieldKey, 'field_')) {
                $fieldKey = self::resolveControllerFieldKey($name, $ruleParent);
            }

            $compiledRule = [
                'field' => $fieldKey,
                'name' => $name,
                'operator' => (string) $rule['operator'],
            ];

            // Preserve falsy values (0, '0', false) that ConditionalLogic::toArray drops.
            if (array_key_exists('value', $rule) && $rule['value'] !== null) {
                $compiledRule['value'] = $rule['value'];
            }

            $compiled[] = $compiledRule;
        }

        return $compiled;
    }

    /**
     * Resolve a controller to the real ACF field key used at registration.
     *
     * Extended ACF hashes keys from the *logical* group key (e.g. `block_hero`), while
     * ACF stores `field['parent']` as the generated `group_*` key. Looking up the sibling
     * by name avoids hashing against the wrong parent.
     */
    private static function resolveControllerFieldKey(string $name, ?string $parentKey): string
    {
        $siblingKey = self::findSiblingFieldKey($name, $parentKey);
        if ($siblingKey !== null) {
            return $siblingKey;
        }

        $resolvedParentKey = Key::resolveParentKey($parentKey, Key::sanitize($name));
        $logicalKey = $resolvedParentKey . '_' . Key::sanitize($name);

        return 'field_' . Key::hash($logicalKey);
    }

    private static function findSiblingFieldKey(string $name, ?string $parentKey): ?string
    {
        if ($parentKey === null || $parentKey === '') {
            return null;
        }

        // Prefer local/raw fields — acf_get_fields() runs acf/load_field, and we are
        // already inside that filter during compile, which would recurse forever.
        $siblings = null;
        if (function_exists('acf_get_local_fields')) {
            $siblings = acf_get_local_fields($parentKey);
        } elseif (function_exists('acf_get_fields')) {
            $siblings = acf_get_fields($parentKey);
        }

        if (!is_array($siblings)) {
            return null;
        }

        return self::findFieldKeyByName($siblings, $name);
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private static function findFieldKeyByName(array $fields, string $name): ?string
    {
        foreach ($fields as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            if (($candidate['name'] ?? null) === $name && !empty($candidate['key'])) {
                return (string) $candidate['key'];
            }

            foreach (['sub_fields', 'layouts'] as $nestedKey) {
                if (empty($candidate[$nestedKey]) || !is_array($candidate[$nestedKey])) {
                    continue;
                }

                // Layouts contain sub_fields one level deeper.
                if ($nestedKey === 'layouts') {
                    foreach ($candidate[$nestedKey] as $layout) {
                        if (!is_array($layout) || empty($layout['sub_fields']) || !is_array($layout['sub_fields'])) {
                            continue;
                        }
                        $found = self::findFieldKeyByName($layout['sub_fields'], $name);
                        if ($found !== null) {
                            return $found;
                        }
                    }
                    continue;
                }

                $found = self::findFieldKeyByName($candidate[$nestedKey], $name);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
