<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Events;

use CloakWP\ACF\Database\Migrations\Observers\EventObserver;

/**
 * Dispatches events to registered observers
 */
class EventDispatcher
{
  /**
   * Registered observers
   * 
   * @var array<EventObserver>
   */
  protected array $observers = [];

  /**
   * Register an observer
   */
  public function addObserver(EventObserver $observer): self
  {
    $this->observers[] = $observer;
    return $this;
  }

  /**
   * Clear all registered observers
   */
  public function clearObservers(): self
  {
    $this->observers = [];
    return $this;
  }

  /**
   * Dispatch an event
   */
  public function dispatch(Event $event): void
  {
    foreach ($this->observers as $observer) {
      $observer->onEvent($event);
    }
  }
}