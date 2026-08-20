<?php

declare(strict_types=1);

namespace CloakWP\ACF\ConditionalChoices;

/**
 * Resolves sibling controller values from an ACF field input name path.
 *
 * Classic form example:
 *   acf[field_block][field_group][field_height]
 * Controller sibling `field_hero_style` becomes:
 *   acf[field_block][field_group][field_hero_style]
 *
 * Gutenberg ACF blocks use `acf-{blockId}[field_…]` and validate against
 * local meta / `$block['data']`, not necessarily `$_POST` under that name —
 * so we fall back to ACF's meta store when request data is missing.
 */
final class SubmittedValueResolver
{
    /**
     * @param list<string> $controllerKeys
     * @param array<string, string> $controllerNames keyed by field key => field name
     * @return array<string, mixed>
     */
    public static function resolveControllers(
        string $inputName,
        array $controllerKeys,
        array $controllerNames = [],
    ): array {
        $values = [];

        foreach ($controllerKeys as $controllerKey) {
            $controllerName = $controllerNames[$controllerKey] ?? null;
            $controllerInput = self::replaceTerminalKey($inputName, $controllerKey);
            $value = self::readFromRequest($controllerInput);

            // Compiled keys can disagree with live ACF keys (block_* vs group_* hashing).
            // Retry $_POST using the live key resolved from the controller field name.
            if ($value === null && is_string($controllerName) && $controllerName !== '') {
                $liveKey = self::resolveLiveFieldKey($controllerName);
                if ($liveKey !== null && $liveKey !== $controllerKey) {
                    $value = self::readFromRequest(
                        self::replaceTerminalKey($inputName, $liveKey),
                    );
                }
            }

            if ($value === null) {
                $value = self::readFromAcfMeta(
                    $inputName,
                    $controllerKey,
                    $controllerName,
                );
            }

            $values[$controllerKey] = $value;
        }

        return $values;
    }

    private static function resolveLiveFieldKey(string $fieldName): ?string
    {
        if (!function_exists('acf_get_field')) {
            return null;
        }

        $field = acf_get_field($fieldName);

        if (!is_array($field) || empty($field['key']) || !is_string($field['key'])) {
            return null;
        }

        return $field['key'];
    }

    public static function replaceTerminalKey(string $inputName, string $replacementKey): string
    {
        if (preg_match('/^(.*)\[([^\]]+)\]$/', $inputName, $matches) !== 1) {
            return $replacementKey;
        }

        return $matches[1] . '[' . $replacementKey . ']';
    }

    public static function readFromRequest(string $inputName): mixed
    {
        $path = self::parseInputPath($inputName);

        if ($path === []) {
            return null;
        }

        $cursor = $_POST;

        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * Resolve a controller from ACF local meta when validating Gutenberg blocks.
     *
     * Input names look like `acf-block_abc123[field_height]` (optionally nested).
     */
    public static function readFromAcfMeta(
        string $inputName,
        string $controllerKey,
        ?string $controllerName = null,
    ): mixed {
        $blockId = self::extractBlockId($inputName);

        if ($blockId === null) {
            return null;
        }

        $value = self::readAcfFieldValue($blockId, $controllerKey);
        if ($value !== null) {
            return $value;
        }

        // Name fallback when compiled controller keys don't match live field keys.
        if ($controllerName) {
            $value = self::readAcfFieldValue($blockId, $controllerName);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private static function readAcfFieldValue(string $blockId, string $selector): mixed
    {
        if (function_exists('acf_get_field') && function_exists('acf_get_value')) {
            $field = acf_get_field($selector);

            if (is_array($field)) {
                return acf_get_value($blockId, $field);
            }
        }

        if (function_exists('get_field_object')) {
            $object = get_field_object($selector, $blockId, false, false);

            if (is_array($object) && array_key_exists('value', $object)) {
                return $object['value'];
            }
        }

        return null;
    }

    public static function extractBlockId(string $inputName): ?string
    {
        if (preg_match('/^acf-(block_[^\[]+)/', $inputName, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    public static function parseInputPath(string $inputName): array
    {
        if ($inputName === '') {
            return [];
        }

        if (!str_contains($inputName, '[')) {
            return [$inputName];
        }

        if (preg_match('/^([^\[\]]+)((?:\[[^\]]*\])*)$/', $inputName, $matches) !== 1) {
            return [];
        }

        $segments = [$matches[1]];

        if ($matches[2] !== '') {
            preg_match_all('/\[([^\]]*)\]/', $matches[2], $bracketMatches);
            foreach ($bracketMatches[1] as $segment) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }
}
