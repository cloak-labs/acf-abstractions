<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Events;

/**
 * Base class for all events
 */
abstract class Event
{
  /**
   * Get the event type
   */
  abstract public function getType(): string;
}