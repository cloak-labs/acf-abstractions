<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields\Resolvers;

/**
 * Interface for field type/collection resolvers.
 * A field resolver converts an ACF field, represented in some custom 
 * format (eg. via a 3rd-party library like Extended ACF or ACF Builder), to the standard array
 * format that ACF uses internally. It follows the Adapter Pattern.
 */
interface FieldResolverInterface
{
  /**
   * Checks if this resolver supports/applies to the given field
   */
  public function supports($field): bool;

  /**
   * For field "type" resolvers, this converts a field to the standard array format that ACF uses internally
   * For field "collection" resolvers, this resolves the collection into an array of fields, preparing them for the field "type" resolvers
   */
  public function resolve($field): array;
}