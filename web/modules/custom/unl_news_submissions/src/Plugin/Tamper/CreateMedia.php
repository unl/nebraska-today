<?php

namespace Drupal\unl_news_submissions\Plugin\Tamper;

use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;
use Drupal\tamper\TamperableItemInterface;
use Drupal\tamper\TamperBase;

/**
 * Plugin implementation for creating a piece of Media from an image URL.
 *
 * @Tamper(
 *   id = "unl_news_submissions_create_media",
 *   label = @Translation("Create Media"),
 *   description = @Translation("Creates a Media item using an image URL."),
 *   category = "Other"
 * )
 */
class CreateMedia extends TamperBase {

  /**
   * {@inheritdoc}
   */
  public function tamper($data, TamperableItemInterface $item = NULL) {
    $file_name = explode("/", $data);
    $file_name = 'announce_' . end($file_name);

    $file_data = file_get_contents($data);
    $file = \Drupal::service('file.repository')
      ->writeData($file_data, 'public://media/images/'.$file_name, FileSystemInterface::EXISTS_REPLACE);
    $media = Media::create([
      'bundle' => 'image',
      'uid' => \Drupal::currentUser()->id(),
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => ' ',
      ],
    ]);
    $media->setName($file_name)
      ->setPublished(TRUE)
      ->save();

    return $media->id();
  }

}
