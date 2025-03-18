<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Observers;

use CloakWP\ACF\Database\Migrations\Events\Event;

/**
 * Interface for event observers
 */
interface EventObserver
{
  /**
   * Handle an event
   */
  public function onEvent(Event $event): void;
}