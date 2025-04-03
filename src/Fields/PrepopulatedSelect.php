<?php

namespace CloakWP\ACF\Fields;

use CloakWP\ACF\Traits\ConfigurableChoices;
use Extended\ACF\Fields\Select;

/**
 * An abstract class for auto-populated ACF select fields.
 */
abstract class PrepopulatedSelect extends Select
{
  use ConfigurableChoices;

  /**
   * Constructor that sets up the field with prepopulated choices.
   */
  public function __construct(string $label, ?string $name = null)
  {
    parent::__construct($label, $name);

    /**
     * Important to call `setChoices` on `acf/init` using a priority < 10 (i.e. just before ACF 
     * field stuff is finalized), giving as much time as possible for WP to finish scaffolding 
     * before `setChoices` runs, as it likely needs to access stuff like CPTs which are only
     * registered/available after a certain point.
     */
    add_action('acf/init', function () {
      $this->setChoices();
    }, 5);
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
}