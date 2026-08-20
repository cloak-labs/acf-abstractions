<?php

declare(strict_types=1);

namespace CloakWP\ACF\ConditionalChoices;

use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Field;
use InvalidArgumentException;

/**
 * Registers the Extended ACF `conditionalChoices()` macro and ACF hooks.
 */
final class Bootstrap
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        // Register macros immediately. Current ACF fires `acf/include_fields` *before*
        // `acf/init` (see ACF::init), so Extended ACF's "macros on acf/init" guidance is
        // too late for field builders that run during include_fields.
        self::registerMacro();

        add_filter('acf/load_field', [self::class, 'compileField'], 20);
        add_filter('acf/field_wrapper_attributes', [self::class, 'addWrapperAttributes'], 10, 2);
        add_filter('acf/validate_value', [self::class, 'validateValue'], 10, 4);
    }

    public static function registerMacro(): void
    {
        if (!class_exists(Field::class) || Field::hasMacro('conditionalChoices')) {
            return;
        }

        Field::macro('conditionalChoices', function (Field $field, array $choiceRules): Field {
            FieldTypeGuard::assert($field);

            foreach ($choiceRules as $groups) {
                $groups = is_array($groups) ? $groups : [$groups];
                foreach ($groups as $group) {
                    if (!$group instanceof ConditionalLogic) {
                        throw new InvalidArgumentException(
                            'conditionalChoices() values must be ConditionalLogic instances or lists of them.',
                        );
                    }
                }
            }

            $id = DefinitionRegistry::put($choiceRules);

            return $field->withSettings([
                'cloakwp_conditional_choices_id' => $id,
            ]);
        });
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public static function compileField(array $field): array
    {
        $id = $field['cloakwp_conditional_choices_id'] ?? null;

        if (!is_string($id) || $id === '') {
            return $field;
        }

        if (!FieldTypeGuard::isSupportedAcfType($field['type'] ?? null)) {
            return $field;
        }

        $definition = DefinitionRegistry::get($id);

        if ($definition === null) {
            return $field;
        }

        /**
         * Allow applications to customize compiled payloads.
         *
         * @param array<string, mixed> $compiled
         * @param array<string, mixed> $field
         * @param array<string, list<\Extended\ACF\ConditionalLogic>> $definition
         */
        $compiled = apply_filters(
            'cloakwp/conditional_choices/compiled',
            DefinitionCompiler::compile($definition, $field),
            $field,
            $definition,
        );

        $field['cloakwp_conditional_choices'] = $compiled;

        return $field;
    }

    /**
     * @param array<string, mixed> $wrapper
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public static function addWrapperAttributes(array $wrapper, array $field): array
    {
        $compiled = $field['cloakwp_conditional_choices'] ?? null;

        if (!is_array($compiled) || empty($compiled['rules'])) {
            return $wrapper;
        }

        $wrapper['data-conditional-choices'] = wp_json_encode($compiled);

        return $wrapper;
    }

    /**
     * @param mixed $valid
     * @param mixed $value
     * @param array<string, mixed> $field
     * @return mixed
     */
    public static function validateValue($valid, $value, array $field, string $inputName)
    {
        if (empty($field['cloakwp_conditional_choices']) && !empty($field['cloakwp_conditional_choices_id'])) {
            $field = self::compileField($field);
        }

        return Validator::validate($valid, $value, $field, $inputName);
    }
}
