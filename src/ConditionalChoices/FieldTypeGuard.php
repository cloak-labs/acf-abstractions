<?php

declare(strict_types=1);

namespace CloakWP\ACF\ConditionalChoices;

use BadMethodCallException;
use Extended\ACF\Fields\ButtonGroup;
use Extended\ACF\Fields\Checkbox;
use Extended\ACF\Fields\Field;
use Extended\ACF\Fields\RadioButton;
use Extended\ACF\Fields\Select;

final class FieldTypeGuard
{
    /** @var list<class-string<Field>> */
    private const SUPPORTED = [
        Select::class,
        RadioButton::class,
        Checkbox::class,
        ButtonGroup::class,
    ];

    public static function assert(Field $field): void
    {
        foreach (self::SUPPORTED as $class) {
            if ($field instanceof $class) {
                return;
            }
        }

        throw new BadMethodCallException(
            'conditionalChoices() is only available on Select, RadioButton, Checkbox, and ButtonGroup fields.',
        );
    }

    public static function isSupportedAcfType(?string $type): bool
    {
        return in_array($type, ['select', 'radio', 'checkbox', 'button_group'], true);
    }
}
