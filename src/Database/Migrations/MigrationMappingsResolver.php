<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\Database\Migrations\Fields\Resolvers\{FieldResolverInterface, DefaultFieldResolver, DefaultFieldCollectionResolver, ExtendedAcfFieldResolver, FieldGroupResolver};
use CloakWP\ACF\Database\Migrations\Fields\{Field, FieldMapping};
use CloakWP\Core\Utils;

/**
 * Resolves and processes ACF field structures for migration
 */
class MigrationMappingsResolver implements MigrationMappingsResolverInterface
{
  /**
   * Field type resolvers registry
   * 
   * @var FieldResolverInterface[]
   */
  protected array $fieldTypeResolvers = [];

  /**
   * Field collection resolvers registry
   * 
   * @var FieldResolverInterface[]
   */
  protected array $fieldCollectionResolvers = [];

  /**
   * Constructor
   * 
   * @param FieldResolverInterface[] $fieldTypeResolvers -- applies to individual fields
   * @param FieldResolverInterface[] $fieldCollectionResolvers -- applies to array of fields (field groups, etc.), before processing individual fields
   */
  public function __construct(array $fieldTypeResolvers = [], array $fieldCollectionResolvers = [])
  {
    // Register default resolvers if none provided
    foreach ($fieldTypeResolvers as $resolver) {
      $this->registerTypeResolver($resolver);
    }

    foreach ($fieldCollectionResolvers as $resolver) {
      $this->registerCollectionResolver($resolver);
    }

    // Register default resolvers after user-provided resolvers, allowing them to override default behaviour
    $this->registerDefaultTypeResolvers();
    $this->registerDefaultCollectionResolvers();
  }

  public function registerTypeResolver(FieldResolverInterface $resolver): static
  {
    $this->fieldTypeResolvers[] = $resolver;
    return $this;
  }

  public function registerCollectionResolver(FieldResolverInterface $resolver): static
  {
    $this->fieldCollectionResolvers[] = $resolver;
    return $this;
  }

  /**
   * Register default field type resolvers
   */
  protected function registerDefaultTypeResolvers(): void
  {
    $this->registerTypeResolver(new DefaultFieldResolver());

    // Only register Extended ACF resolver if the package is available
    if (class_exists('\\Extended\\ACF\\Fields\\Field')) {
      $this->registerTypeResolver(new ExtendedAcfFieldResolver());
    }
  }

  /**
   * Register default field collection resolvers
   */
  protected function registerDefaultCollectionResolvers(): void
  {
    $this->registerCollectionResolver(new FieldGroupResolver());
    $this->registerCollectionResolver(new DefaultFieldCollectionResolver());
  }

  /**
   * Resolve a migration (converts provided fields to arrays and generates field mappings)
   */
  public function resolve(MigrationInterface $migration): ResolvedMigration
  {
    $nameChanges = $migration->getNameChanges();
    $oldFields = $this->flatten($migration->getOldFields());
    $newFields = $this->flatten($migration->getNewFields());
    $fieldMappings = $this->generateFieldMappings($oldFields, $newFields, $nameChanges);

    return new ResolvedMigration($migration, $oldFields, $newFields, $fieldMappings);
  }

  /**
   * Find an appropriate resolver for a field
   */
  protected function findFieldTypeResolver(mixed $field): ?FieldResolverInterface
  {
    return $this->findResolver($field, $this->fieldTypeResolvers);
  }

  /**
   * Find an appropriate resolver for a field collection
   */
  protected function findFieldCollectionResolver(mixed $fields): ?FieldResolverInterface
  {
    return $this->findResolver($fields, $this->fieldCollectionResolvers);
  }

  protected function findResolver(mixed $field, array $resolvers): ?FieldResolverInterface
  {
    foreach ($resolvers as $resolver) {
      if ($resolver->supports($field))
        return $resolver;
    }

    return null;
  }

  /**
   * Convert a field to an array using the appropriate resolver
   */
  protected function resolveFieldType(mixed $field): ?array
  {
    $resolver = $this->findFieldTypeResolver($field);

    if (!$resolver) {
      throw new \InvalidArgumentException(
        'No resolver found for field type: ' . (is_object($field) ? get_class($field) : gettype($field))
      );
    }

    return $resolver->resolve($field);
  }

  /**
   * Resolve a field collection into an array of fields, preparing them for the field "type" resolvers
   */
  protected function resolveFieldCollection(mixed $fields): array
  {
    $resolver = $this->findFieldCollectionResolver($fields);

    if (!$resolver) {
      throw new \InvalidArgumentException(
        'No resolver found for field collection: ' . (is_object($fields) ? get_class($fields) : gettype($fields))
      );
    }

    return $resolver->resolve($fields);
  }

  /**
   * Flatten a nested field structure, while resolving field collections and types, and return a map of field keys to field data
   * 
   * @return array<string, Field>
   */
  protected function flatten(mixed $fields, string $parentKey = ''): array
  {
    $result = [];
    $fields = $this->resolveFieldCollection($fields);

    foreach ($fields as $field) {
      // Convert field to the array format that ACF uses internally
      $fieldData = $this->resolveFieldType($field);

      if (!isset($fieldData['name']) || !isset($fieldData['key'])) {
        throw new \InvalidArgumentException('Field must have a name and key');
      }

      $name = $fieldData['name'];

      // Store the field with its full name for better matching
      $fullName = $parentKey ? "{$parentKey}_{$name}" : $name;
      $fieldData['full_name'] = $fullName;
      $result[$fieldData['key']] = new Field($fieldData);

      // Process sub-fields if they exist
      if (!empty($fieldData['sub_fields'])) {
        $subFields = $this->flatten($fieldData['sub_fields'], $fullName);
        $result = array_merge($result, $subFields);
      }

      // Process layouts for flexible content
      if (!empty($fieldData['layouts'])) {
        foreach ($fieldData['layouts'] as $layout) {
          if (!empty($layout['sub_fields'])) {
            $subFields = $this->flatten($layout['sub_fields'], "{$fullName}_{$layout['name']}");
            $result = array_merge($result, $subFields);
          }
        }
      }
    }

    return $result;
  }

  /**
   * Generate mappings between old and new fields
   * 
   * @param Field[] $oldFields
   * @param Field[] $newFields
   * @return FieldMapping[]
   */
  protected function generateFieldMappings(array $oldFields, array $newFields, array $nameChanges = []): array
  {
    $fieldMappings = [];
    $oldFieldsCopy = Utils::deepCopy($oldFields);
    $newFieldsCopy = Utils::deepCopy($newFields);

    // First try to match by exact full_name
    foreach ($oldFieldsCopy as $oldKey => $oldField) {
      foreach ($newFieldsCopy as $newKey => $newField) {
        if ($oldField->getFullName() === $newField->getFullName()) {
          // if ($oldField->hasChangedFrom($newField)) {
          $fieldMappings[] = new FieldMapping($oldField, $newField);
          // }
          unset($newFieldsCopy[$newKey]);
          unset($oldFieldsCopy[$oldKey]);
          break;
        }
      }
    }

    // For fields that didn't exactly match by full_name, try to match against user-provided name changes
    foreach ($oldFieldsCopy as $oldKey => $oldField) {
      foreach ($newFieldsCopy as $newKey => $newField) {
        $oldFullName = $oldField->getFullName();
        $newFullName = $newField->getFullName();
        $oldName = $oldField->getName();
        $newName = $newField->getName();

        // Check explicit name changes
        if (isset($nameChanges[$oldFullName]) && $nameChanges[$oldFullName] === $newFullName) {
          $fieldMappings[] = new FieldMapping($oldField, $newField);
          unset($newFieldsCopy[$newKey]);
          unset($oldFieldsCopy[$oldKey]);
          break;
        }

        if (isset($nameChanges[$oldName]) && $nameChanges[$oldName] === $newName) {
          $fieldMappings[] = new FieldMapping($oldField, $newField);
          unset($newFieldsCopy[$newKey]);
          unset($oldFieldsCopy[$oldKey]);
          break;
        }
      }
    }

    // For remaining unmapped fields, try to match against closest full_name using path similarity scoring. This can sometimes detect name changes automatically, but it can also lead to false positives, so it's best to be explicit and use the nameChanges method when creating your Migration.
    foreach ($oldFieldsCopy as $oldKey => $oldField) {
      $bestMatch = null;
      $bestMatchScore = 0;

      foreach ($newFieldsCopy as $newKey => $newField) {
        $oldName = $oldField->getName();
        $newName = $newField->getName();

        // Must have the same name (unless it was explicitly renamed)
        if ($oldName !== $newName && !isset($nameChanges[$oldName])) {
          continue;
        }

        // Calculate full_name similarity score
        $score = $this->calculatePathSimilarity($oldField->getFullName(), $newField->getFullName());
        // if ($score > $bestMatchScore && $oldField->hasChangedFrom($newField)) {
        if ($score > $bestMatchScore) {
          $bestMatchScore = $score;
          $bestMatch = $newField;
        }
      }

      if ($bestMatch) {
        $fieldMappings[] = new FieldMapping($oldField, $bestMatch);
      }
    }

    return $fieldMappings;
  }

  /**
   * Calculate similarity between two field paths
   */
  protected function calculatePathSimilarity(string $path1, string $path2): int
  {
    $parts1 = explode('_', $path1);
    $parts2 = explode('_', $path2);

    // Count matching segments from the end (field name should match)
    $score = 0;
    $i = count($parts1) - 1;
    $j = count($parts2) - 1;

    while ($i >= 0 && $j >= 0) {
      if ($parts1[$i] === $parts2[$j]) {
        $score += 1;
      } else {
        break;
      }
      $i--;
      $j--;
    }

    return $score;
  }
}