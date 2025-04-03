<?php

namespace CloakWP\ACF\FieldSets;

use CloakWP\ACF\FieldSet;
use Extended\ACF\Fields\{Group, Text, TrueFalse, Image, File, Select};
use Extended\ACF\ConditionalLogic;

/**
 * A preset of fields for video file uploads + configuration.
 */
class Video implements FieldSet
{
  public static function fields(): array
  {
    return [
      Select::make('Video Source', 'src')
        ->choices([
          'url' => 'Embed from URL',
          'file' => 'MP4 File',
        ])
        ->default('url'),
      Text::make('Video Embed URL', 'url')
        ->conditionalLogic([
          ConditionalLogic::where('src', '!=', 'file')
        ]),
      Group::make('Video Files', 'files')
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
      TrueFalse::make('Loop Video', 'loop')
        ->stylized(),
      Image::make('Thumbnail Image', 'thumbnail')
    ];
  }
}
