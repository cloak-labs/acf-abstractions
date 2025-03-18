<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Observers;

use CloakWP\ACF\Database\Migrations\Events\FieldKeyChangeEvent;
use CloakWP\ACF\Database\Migrations\Events\FieldNameChangeEvent;
use CloakWP\ACF\Database\Migrations\Events\OperationEvent;

/**
 * Interface for field change observers
 */
interface FieldChangeObserver
{
  /**
   * Handle a field key change event
   */
  public function onFieldKeyChange(FieldKeyChangeEvent $event): void;

  /**
   * Handle a field name change event
   */
  public function onFieldNameChange(FieldNameChangeEvent $event): void;

  /**
   * Handle an operation event
   */
  public function onOperation(OperationEvent $event): void;
}