<?php

namespace CloakWP\ACF\Traits;

trait ConfigurableChoices
{
  public function appendChoices(array $choices): static
  {
    $callback = function () use ($choices) {
      $this->choices(array_merge($this->settings['choices'] ?? [], $choices));
    };

    $this->runCallback($callback);

    return $this;
  }

  public function prependChoices(array $choices): static
  {
    $callback = function () use ($choices) {
      $this->choices(array_merge($choices, $this->settings['choices'] ?? []));
    };

    $this->runCallback($callback);

    return $this;
  }

  public function removeChoices(array $keysToRemove): static
  {
    $callback = function () use ($keysToRemove) {
      $filteredChoices = array_diff_key($this->settings['choices'] ?? [], array_flip($keysToRemove));
      $this->choices($filteredChoices);
    };

    $this->runCallback($callback);

    return $this;
  }

  public function enabledChoices(array $enabledChoices): static
  {
    $callback = function () use ($enabledChoices) {
      $this->choices(array_intersect_key($this->settings['choices'] ?? [], array_flip($enabledChoices)));
    };

    $this->runCallback($callback);

    return $this;
  }

  protected function runCallback(callable $callback): void
  {
    add_action('acf/init', $callback, 8); // Higher priority than initial setup (less than 10 in order to run before ACF renders the field)
  }
}