<?php

declare(strict_types=1);

namespace CloakWP\ACF\ConditionalChoices;

use Extended\ACF\ConditionalLogic;
use InvalidArgumentException;
use RuntimeException;

/**
 * Request-local registry of declarative conditional-choice definitions.
 *
 * Definitions stay out of ACF's persisted field arrays until compiled into
 * primitive payloads during acf/load_field.
 */
final class DefinitionRegistry
{
    /** @var array<string, array<string, list<ConditionalLogic>>> */
    private static array $definitions = [];

    /**
     * @param array<string, list<ConditionalLogic>|ConditionalLogic> $choiceRules
     */
    public static function put(array $choiceRules): string
    {
        $normalized = self::normalize($choiceRules);
        $id = 'cc_' . md5(serialize(array_keys($normalized)) . uniqid('', true));
        self::$definitions[$id] = $normalized;

        return $id;
    }

    /**
     * @return array<string, list<ConditionalLogic>>|null
     */
    public static function get(string $id): ?array
    {
        return self::$definitions[$id] ?? null;
    }

    /**
     * @param array<string, list<ConditionalLogic>|ConditionalLogic> $choiceRules
     * @return array<string, list<ConditionalLogic>>
     */
    private static function normalize(array $choiceRules): array
    {
        if ($choiceRules === []) {
            throw new InvalidArgumentException('conditionalChoices() requires at least one choice rule.');
        }

        $normalized = [];

        foreach ($choiceRules as $choiceValue => $groups) {
            if (!is_string($choiceValue) && !is_int($choiceValue)) {
                throw new InvalidArgumentException('conditionalChoices() choice keys must be strings or integers.');
            }

            $choiceKey = (string) $choiceValue;

            if (!is_array($groups)) {
                $groups = [$groups];
            }

            if ($groups === []) {
                throw new InvalidArgumentException(
                    "conditionalChoices() choice [{$choiceKey}] must include at least one ConditionalLogic group.",
                );
            }

            $normalizedGroups = [];

            foreach ($groups as $group) {
                if (!$group instanceof ConditionalLogic) {
                    throw new InvalidArgumentException(
                        "conditionalChoices() choice [{$choiceKey}] values must be ConditionalLogic instances.",
                    );
                }

                if ($group->rules === []) {
                    throw new InvalidArgumentException(
                        "conditionalChoices() choice [{$choiceKey}] contains an empty ConditionalLogic group.",
                    );
                }

                $normalizedGroups[] = $group;
            }

            $normalized[$choiceKey] = $normalizedGroups;
        }

        return $normalized;
    }

    public static function reset(): void
    {
        self::$definitions = [];
    }

    public static function require(string $id): array
    {
        $definition = self::get($id);

        if ($definition === null) {
            throw new RuntimeException("Unknown conditionalChoices definition [{$id}].");
        }

        return $definition;
    }
}
