<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class DefaultPostMapper
{
  public function __invoke(object $post): array
  {
    return [
      'id' => (int) ($post->ID ?? 0),
      'title' => (string) ($post->post_title ?? ''),
      'slug' => (string) ($post->post_name ?? ''),
      'excerpt' => (string) ($post->post_excerpt ?? ''),
      'date' => (string) ($post->post_date ?? ''),
      'status' => (string) ($post->post_status ?? ''),
    ];
  }
}
