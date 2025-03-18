<?php

namespace CloakWP\ACF\Fields;

use CloakWP\Core\PostReturnType;
use CloakWP\Core\Utils;
use CloakWP\ACF\Traits\ConfigurableChoices;

/**
 * An auto-populated ACF select field that allows you to select a WordPress post type.
 */
class PostTypeSelect extends PrepopulatedSelect
{
  use ConfigurableChoices;

  // Post types need to be loaded after init
  protected ?string $setHook = 'after_setup_theme';

  protected function setChoices(): void
  {
    $customPostTypes = Utils::getCustomPostTypes(PostReturnType::Objects);
    $publicPostTypes = Utils::getPublicPostTypes(PostReturnType::Objects);

    $exclude = ['attachment'];
    $postTypes = [];
    foreach (array_merge($customPostTypes, $publicPostTypes) as $slug => $post) {
      // Bail early if post type is excluded
      if (in_array($slug, $exclude))
        continue;
      $postTypes[$slug] = $post->labels->singular_name;
    }

    $this->choices($postTypes);
  }
}
