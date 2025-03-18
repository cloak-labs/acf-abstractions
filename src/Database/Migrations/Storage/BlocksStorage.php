<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Storage;

/**
 * Storage location for ACF fields stored in Gutenberg blocks
 */
class BlocksStorage extends AbstractStorageLocation
{
  /**
   * Read operation is not applicable for blocks
   */
  public function read(string $fieldName, bool $isKeyRef = false, ?string $value = null): mixed
  {
    // Not applicable for blocks
    return false;
  }

  /**
   * Write operation is not applicable for blocks
   */
  public function update(string $oldFieldName, string $newFieldName, mixed $newFieldValue, bool $isKeyRef = false): int
  {
    // Not applicable for blocks
    return 0;
  }

  /**
   * Delete operation is not applicable for blocks
   */
  public function delete(string $fieldName, bool $isKeyRef = false): int
  {
    // Not applicable for blocks
    return 0;
  }

  /**
   * Find blocks containing ACF fields
   */
  public function findFields(string $pattern): array
  {
    global $wpdb;

    // Get posts that might contain ACF blocks
    return $wpdb->get_results($wpdb->prepare(
      "SELECT ID, post_content FROM {$wpdb->posts} 
             WHERE post_content LIKE %s 
             OR post_content LIKE %s",
      '%<!-- wp:acf/%',
      '%\"_field_%'  // This pattern looks for ACF field key references
    ));
  }

  /**
   * Get the storage type name
   */
  public function getStorageType(): string
  {
    return 'blocks';
  }

  /**
   * Process and update blocks in post content
   */
  public function processBlocks(array $keyMappings, array $oldFields, array $newFields): array
  {
    global $wpdb;
    $results = ['blocks' => 0];

    $posts = $this->findFields('');

    foreach ($posts as $post) {
      $content = $post->post_content;
      $modified = false;

      // Find all ACF block comments
      if (preg_match_all('/<!-- wp:acf\/.*?-->(.*?)<!-- \/wp:acf\/.*?-->/s', $content, $matches)) {
        foreach ($matches[1] as $blockContent) {
          $originalBlockContent = $blockContent;

          // Extract JSON data from block
          if (preg_match('/{.*}/s', $blockContent, $jsonMatches)) {
            $jsonData = $jsonMatches[0];
            $data = json_decode($jsonData, true);

            if ($data && isset($data['data'])) {
              // Process the data to update field keys
              $updatedData = $this->processBlockData($data, $keyMappings, $oldFields, $newFields);

              // If changes were made, update the block content
              if ($updatedData !== $data) {
                $updatedJson = json_encode($updatedData);
                $updatedBlockContent = str_replace($jsonData, $updatedJson, $blockContent);
                $content = str_replace($originalBlockContent, $updatedBlockContent, $content);
                $modified = true;
                $results['blocks']++;
              }
            }
          }
        }
      }

      // Update the post if changes were made
      if ($modified && !$this->isDryRun) {
        $wpdb->update(
          $wpdb->posts,
          ['post_content' => $content],
          ['ID' => $post->ID]
        );
      }
    }

    return $results;
  }

  /**
   * Process ACF Block data to update field keys
   */
  private function processBlockData($data, $keyMappings, $oldFields, $newFields, $depth = 0)
  {
    // Add recursion depth limit
    if ($depth > 10 || !is_array($data)) {
      return $data;
    }

    // Process the data property which contains the field values and references
    if (isset($data['data']) && is_array($data['data'])) {
      foreach ($data['data'] as $fieldName => $fieldValue) {
        // Check if this is a key reference field (starts with underscore)
        if (strpos($fieldName, '_') === 0) {
          $actualFieldName = substr($fieldName, 1);

          // If this is a field key that needs to be updated
          if (isset($keyMappings[$fieldValue])) {
            $data['data'][$fieldName] = $keyMappings[$fieldValue];
            $newFieldName = $this->getNewFieldName($fieldValue, $keyMappings, $newFields);

            // If the field name changed, update both the value and reference keys
            if ($newFieldName && $newFieldName !== $actualFieldName) {
              // Move the value to the new field name
              if (isset($data['data'][$actualFieldName])) {
                $data['data'][$newFieldName] = $data['data'][$actualFieldName];
                unset($data['data'][$actualFieldName]);
              }

              // Move the key reference to the new field name
              $data['data']['_' . $newFieldName] = $data['data'][$fieldName];
              unset($data['data'][$fieldName]);
            }
          }
        }
      }

      // Process any nested arrays that might contain serialized field data
      foreach ($data['data'] as $fieldName => $fieldValue) {
        if (is_array($fieldValue)) {
          $data['data'][$fieldName] = $this->processBlockData(
            $fieldValue,
            $keyMappings,
            $oldFields,
            $newFields,
            $depth + 1
          );
        }
      }
    }

    // Process any other properties recursively
    foreach ($data as $key => $value) {
      if ($key !== 'data' && is_array($value)) {
        $data[$key] = $this->processBlockData(
          $value,
          $keyMappings,
          $oldFields,
          $newFields,
          $depth + 1
        );
      }
    }

    return $data;
  }

  /**
   * Get the new field name for an old field key
   */
  private function getNewFieldName(string $oldKey, array $keyMappings, array $newFields): string
  {
    if (!isset($keyMappings[$oldKey])) {
      return '';
    }

    $newKey = $keyMappings[$oldKey];
    return $newFields[$newKey]['full_name'] ?? '';
  }
}