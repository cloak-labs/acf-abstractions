<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class WordPressQueryExecutor implements QueryExecutor
{
  public function getPosts(array $args): array
  {
    if (!class_exists(\WP_Query::class)) {
      return [];
    }

    $query = new \WP_Query($args);
    $posts = is_array($query->posts) ? array_values($query->posts) : [];

    if ($posts === []) {
      return [];
    }

    $ids = [];
    foreach ($posts as $post) {
      if (is_object($post) && isset($post->ID)) {
        $ids[] = (int) $post->ID;
      }
    }

    if ($ids !== [] && function_exists('update_postmeta_cache')) {
      update_postmeta_cache($ids);
    }

    if ($ids !== [] && function_exists('update_object_term_cache')) {
      update_object_term_cache($ids, (array) ($args['post_type'] ?? ['post']));
    }

    return $posts;
  }
}
