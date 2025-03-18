<?php

namespace CloakWP\ACF\Fields;

use Extended\ACF\Fields\Select;

/**
 * An abstract class for auto-populated ACF select fields.
 */
abstract class PrepopulatedSelect extends Select
{
  /**
   * The WordPress action hook to use for setting choices.
   * Set to null to set choices immediately without a hook.
   * @var string|null
   */
  protected ?string $setHook = null;

  /**
   * Constructor that sets up the field with prepopulated choices.
   */
  public function __construct(string $label, ?string $name = null)
  {
    parent::__construct($label, $name);

    if ($this->setHook) {
      add_action($this->setHook, function () {
        $this->setChoices();
      }, 10);
    } else {
      $this->setChoices();
    }
  }

  /**
   * Override inherited `make` to ensure proper initialization
   */
  public static function make(string $label, string|null $name = null): static
  {
    return new static($label, $name);
  }

  /**
   * Abstract method that must be implemented by child classes to set the prepopulated choices.
   */
  abstract protected function setChoices(): void;

  /**
   * Include only specific choices in the select field.
   */
  public function include(array $enabledChoices): self
  {
    $callback = function () use ($enabledChoices) {
      $validChoices = $this->settings['choices'];
      $choices = [];

      if ($enabledChoices) {
        foreach ($enabledChoices as $choice) {
          if (!array_key_exists($choice, $validChoices)) {
            continue;
          }

          // Set the choices field based on enabled choices
          $choices[$choice] = $validChoices[$choice];
        }
      } else {
        $choices = $validChoices;
      }

      // Set filtered choices
      $this->choices($choices);
    };

    if ($this->setHook) {
      add_action($this->setHook, $callback, 10);
    } else {
      $callback();
    }

    return $this;
  }
}