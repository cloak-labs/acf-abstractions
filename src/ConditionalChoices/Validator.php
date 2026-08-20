<?php

declare(strict_types=1);

namespace CloakWP\ACF\ConditionalChoices;

final class Validator
{
    /**
     * @param mixed $valid
     * @param mixed $value
     * @param array<string, mixed> $field
     * @return mixed
     */
    public static function validate($valid, $value, array $field, string $inputName)
    {
        if ($valid !== true) {
            return $valid;
        }

        $compiled = $field['cloakwp_conditional_choices'] ?? null;

        if (!is_array($compiled) || empty($compiled['rules'])) {
            return $valid;
        }

        $submitted = self::normalizeSubmittedValues($value);

        foreach ($submitted as $submittedValue) {
            if (!in_array($submittedValue, $compiled['canonical_choices'] ?? [], true)) {
                return __('Selected value is not a valid choice for this field.', 'cloakwp');
            }
        }

        // Always-available choices (no rules) do not need controllers.
        $ruledSubmitted = array_values(array_filter(
            $submitted,
            static fn(string $choice): bool => isset($compiled['rules'][$choice]),
        ));

        if ($ruledSubmitted === []) {
            return $valid;
        }

        $controllers = self::collectControllersForChoices($compiled, $ruledSubmitted);
        $controllerKeys = array_keys($controllers);
        $controllerValues = SubmittedValueResolver::resolveControllers(
            $inputName,
            $controllerKeys,
            $controllers,
        );

        foreach ($controllerKeys as $controllerKey) {
            if (!array_key_exists($controllerKey, $controllerValues) || $controllerValues[$controllerKey] === null) {
                return __('One or more controlling fields required by this field are missing from the submission.', 'cloakwp');
            }
        }

        $available = RuleEvaluator::availableChoices($compiled, $controllerValues);

        foreach ($ruledSubmitted as $submittedValue) {
            if (!in_array($submittedValue, $available, true)) {
                return __('Selected value is not available for the current field configuration.', 'cloakwp');
            }
        }

        return $valid;
    }

    /**
     * @param array<string, mixed> $compiled
     * @param list<string> $choices
     * @return array<string, string> fieldKey => fieldName
     */
    private static function collectControllersForChoices(array $compiled, array $choices): array
    {
        $controllers = [];

        foreach ($choices as $choice) {
            // rules[choice] = list of OR groups; each group is a list of AND rules.
            foreach ($compiled['rules'][$choice] ?? [] as $andGroup) {
                foreach ($andGroup as $rule) {
                    if (!empty($rule['field'])) {
                        $controllers[(string) $rule['field']] = (string) ($rule['name'] ?? '');
                    }
                }
            }
        }

        return $controllers;
    }

    /**
     * @return list<string>
     */
    private static function normalizeSubmittedValues(mixed $value): array
    {
        if ($value === null || $value === '' || $value === false) {
            return [];
        }

        if (is_array($value)) {
            return array_map('strval', array_values($value));
        }

        return [(string) $value];
    }
}
