<?php

namespace CloakWP\ACF\Fields;

use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Image;
use Extended\ACF\Fields\Text;
use Extended\ACF\Fields\Select;
use Extended\ACF\Fields\File;
use Extended\ACF\Fields\TrueFalse;
use CloakWP\ACF\ConfigurableGroupPreset;

class Video extends ConfigurableGroupPreset
{
  protected function defaultFields(): array
  {
    return [
      'src' => Select::make('Video Source', 'src')
        ->choices([
          'youtube' => 'Youtube',
          'vimeo' => 'Vimeo',
          'file' => 'MP4 File',
        ])
        ->default('youtube'),
      'url' => Text::make('Video Embed URL', 'url')
        ->conditionalLogic([
          ConditionalLogic::where('src', '!=', 'file')
        ]),
      'files' => Group::make('Video Files', 'files')
        ->fields([
          File::make('Standard Video File', 'h264')
            ->helperText('Upload a standard MP4 file (H.264 codec).')
            ->acceptedFileTypes(['mp4'])
            ->required(),
          File::make('High-Efficiency Video File', 'av1')
            ->helperText('Optional: upload your video file in WebM format using the AV1 codec. This ensures better quality and performance over a standard MP4 file, but has less browser support — so you must also upload an MP4 as a fallback.')
            ->acceptedFileTypes(['webm']),
        ])
        ->conditionalLogic([
          ConditionalLogic::where('src', '==', 'file')
        ]),
      'loop' => TrueFalse::make('Loop Video', 'loop')
        ->stylized(),
      'thumbnail' => Image::make('Thumbnail Image', 'thumbnail')
        ->format('id')
    ];
  }
}
