<?php

namespace CloakWP\ACF\Fields;

use Extended\ACF\Fields\ButtonGroup;
use InvalidArgumentException;

/**
 * An auto-populated ACF Button Group field that allows you to select a vertical alignment (top, center, bottom), with icon indicators.
 */
class VerticalAlignment extends ButtonGroup
{
  protected array $enabledChoices;

  // we override inherited `make` in order to set default alignment options when include() isn't called/specified
  public static function make(string $label, string|null $name = null): static
  {
    $self = new static($label, $name);
    $self->include(); // set defaults
    return $self;
  }


  public function include(array $enabledChoices = ['top', 'center', 'bottom']): self
  {
    $validChoices = ['top', 'center', 'bottom'];

    foreach ($enabledChoices as $choice) {
      if (!in_array($choice, $validChoices)) {
        throw new InvalidArgumentException("Invalid alignment choice: $choice");
      }
    }

    // Set the choices field based on enabled choices
    $choices = [];
    foreach ($enabledChoices as $choice) {
      switch ($choice) {
        case 'top':
          $choices['top'] = '<svg width="100%" height="100%" viewBox="0 0 48 42" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;">
            <g transform="matrix(1,0,0,1,-516.32,-326.292)">
              <g transform="matrix(1.92375e-17,-0.314173,-1.13333,-6.93967e-17,933.711,450.415)">
                <rect x="380.162" y="326.292" width="14.919" height="41.995" style="fill:rgb(57,84,232);"/>
              </g>
              <g transform="matrix(1.02528,0,0,-0.775772,132.234,621.414)">
                <rect x="380.162" y="326.292" width="14.919" height="41.995" style="fill:rgb(69,69,69);"/>
              </g>
              <g transform="matrix(1.02528,0,0,-0.64484,153.157,573.194)">
                <rect x="380.162" y="326.292" width="14.919" height="41.995" style="fill:rgb(69,69,69);"/>
              </g>
            </g>
          </svg>';
          break;
        case 'center':
          $choices['center'] = '<svg width="100%" height="100%" viewBox="0 0 48 42" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;">
            <g transform="matrix(1,0,0,1,-374.542,-326.292)">
              <g transform="matrix(1.92375e-17,0.314173,-1.13333,6.93967e-17,791.934,225.509)">
                <rect x="380.162" y="326.292" width="14.919" height="41.995" style="fill:rgb(57,84,232);"/>
              </g>
              <g transform="matrix(1.02528,0,0,1,-9.54432,0)">
                <rect x="380.162" y="326.292" width="14.919" height="41.995" style="fill:rgb(69,69,69);"/>
              </g>
              <g transform="matrix(1.02528,0,0,0.719298,11.3793,97.4846)">
                <rect x="380.162" y="326.292" width="14.919" height="41.995" style="fill:rgb(69,69,69);"/>
              </g>
            </g>
          </svg>';
          break;
        case 'bottom':
          $choices['bottom'] = '<svg width="100%" height="100%" viewBox="0 0 48 42" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;">
            <g transform="matrix(1,0,0,1,-444.948,-326.292)">
              <g transform="matrix(1.92375e-17,0.314173,-1.13333,6.93967e-17,862.339,244.163)">
                <rect x="380.162" y="326.292" width="14.919" height="41.995" style="fill:rgb(57,84,232);"/>
              </g>
              <g transform="matrix(1.02528,0,0,0.775772,60.8611,73.1638)">
                <rect x="380.162" y="326.292" width="14.919" height="41.995" style="fill:rgb(69,69,69);"/>
              </g>
              <g transform="matrix(1.02528,0,0,0.64484,81.7848,121.384)">
                <rect x="380.162" y="326.292" width="14.919" height="41.995" style="fill:rgb(69,69,69);"/>
              </g>
            </g>
          </svg>';
          break;
      }
    }

    // Add choices to settings
    $this->settings['choices'] = $choices;
    $this->settings['allow_html'] = 1; // Enable HTML in choices
    $this->enabledChoices = $enabledChoices;

    return $this;
  }
}