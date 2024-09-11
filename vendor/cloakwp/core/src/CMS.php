<?php

namespace CloakWP\Core;

use CloakWP\Core\Enqueue\Script;
use CloakWP\Core\Enqueue\Stylesheet;
use Snicco\Component\BetterWPAPI\BetterWPAPI;

/**
 * A class that provides a simpler API around some core WordPress functions
 */
class CMS extends BetterWPAPI
{

  /**
   * Initialize the class and set its properties.
   */
  public function __construct()
  {
  }

  /**
   * Enqueue a single Stylesheet or Script
   */
  public function enqueueAsset(Stylesheet|Script $asset): static
  {
    $asset->enqueue();
    return $this;
  }

  /**
   * Enqueue an array of Stylesheets and/or Scripts
   */
  public function enqueueAssets(array $assets): static
  {
    foreach ($assets as $asset) {
      $this->enqueueAsset($asset);
    }
    return $this;
  }
}
