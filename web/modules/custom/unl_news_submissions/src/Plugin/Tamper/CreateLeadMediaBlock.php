<?php

namespace Drupal\unl_news_submissions\Plugin\Tamper;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;
use Drupal\tamper\TamperableItemInterface;
use Drupal\tamper\TamperBase;

/**
 * Plugin implementation for creating a Lead Media block using an image URL.
 *
 * @Tamper(
 *   id = "unl_news_submissions_create_lead_media",
 *   label = @Translation("Create Lead Media block (old and unused)"),
 *   description = @Translation("Creates a Lead Media block using an image URL."),
 *   category = "Other"
 * )
 */
class CreateLeadMediaBlock extends TamperBase {

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

    $lead_image_block = BlockContent::create([
      'info' => 'Lead image from Announce Submission',
      'type' => 'parallax_image',
      'langcode' => 'en',
      'field_title_placement' => 'Basic',
      'field_image' => $media->id(),
    ]);
    $lead_image_block->save();

    return $lead_image_block->id();
  }

}
