<?php

namespace Drupal\unl_news_migrate\Form;

use DOMDocument;
use DOMXPath;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\pathauto\PathautoState;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a form for importing entities from the old Nebraska Today.
 *
 * @ingroup unl_news_migrate
 */
class ImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'unl_news_migrate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#prefix'] = '<p>Nebraska Today 7 to 10 migrator.</p>';

    $form['actions'] = array(
      '#type' => 'actions',
      'submit' => array(
        '#type' => 'submit',
        '#value' => 'Proceed',
      ),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $base_url = 'https://news.unl.edu/';
    $base_url = trim($base_url, '/') . '/';

    $url = 'https://news.unl.edu/drupal-10-migration-articles.xml?dhgf';
    //$url = 'https://localhost.unl.edu/drupal-10-migration-articles.xml';
    $request = \Drupal::httpClient()->get($url);
    $body = $request->getBody();
    $nodes_to_import = simplexml_load_string($body);

    $batch = [
      'title' => t('Importing articles from news.unl.edu'),
      'operations' => [],
      'init_message' => t('Import process is starting.'),
      'progress_message' => t('Processed @current out of @total. Estimated time: @estimate.'),
      'error_message' => t('The process has encountered an error.'),
    ];

    foreach ($nodes_to_import->node as $item) {
      $path = (string)$item->path;
      $url = trim($base_url, '/') . $path;
      $nid = (int)$item->nid;
      $changed = (int)$item->changed;
      $created = (int)$item->created;
      $uuid = (string)$item->uuid;
      $alias = substr($url, strlen($base_url)-1);
      $summary = (string)$item->summary;
      $tags = (array)$item->tag;
      $news_release_contacts = (array)$item->news_release_contacts;
      $sections = (array)$item->section;
      $home_page_section = (string)$item->home_page_section;

      $batch['operations'][] = [
        ['\Drupal\unl_news_migrate\Form\ImportForm', 'importPage'], [$url, $nid, $changed, $created, $uuid, $alias, $summary, $tags, $news_release_contacts, $sections, $home_page_section]
      ];
    }

    batch_set($batch);
    \Drupal::messenger()->addMessage('Imported ' . count($nodes_to_import) . ' articles!');

    $form_state->setRebuild(TRUE);
  }

  /**
   * Imports an article node.
   */
  public static function importPage($url, $nid, $changed, $created, $uuid, $alias, $summary, $tags, $news_release_contacts, $sections, $home_page_section, &$context) {
    $request = \Drupal::httpClient()->get($url . '?asdf');
    $body = $request->getBody();
    if (!$body) {
      $context['message'] = t('The page at ' . $url . ' is empty. Ignoring.');
      return false;
    }

    $dom = new DOMDocument();
    if (!@$dom->loadHTML($body)) {
      return false;
    }
    $xpath = new DOMXpath($dom);

    // Check to see if there's a base tag on this page.
    $base_tags = $dom->getElementsByTagName('base');
    $page_base = NULL;
    if ($base_tags->length > 0) {
      $page_base = $base_tags->item(0)->getAttribute('href');
    }

    // Title.
    $title = $url;
    $nodes = $xpath->query("//header[@id='dcf-page-title']/h1//text()");
    if ($nodes->length > 0) {
      $title = $dom->saveHTML($nodes->item(0));
    }

    // Subtitle.
    $subtitle = null;
    $nodes = $xpath->query("//header/div[contains(@class, 'field-name-field-subtitle')]//text()");
    if ($nodes->length > 0) {
      $subtitle = trim($dom->saveHTML($nodes->item(0)));
    }

    // Written by.
    $written_by_term = null;
    $nodes = $xpath->query("//header/div[contains(@class, 'field-name-field-written-by')]/a//text()");
    if ($nodes->length > 0) {
      $written_by = $dom->saveHTML($nodes->item(0));
      $written_by_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $written_by, 'vid' => 'written_by']);
      $written_by_term = reset($written_by_term);
      if (!$written_by_term) {
        $written_by_term = Term::create(['name' => $written_by, 'vid' => 'written_by']);
        $written_by_term->save();
      }
    }

    // Body field.
    $nodes = $xpath->query("//div[contains(@class, 'field-name-body')]");
    if (!$bodyNode = $nodes->item(0)) {
      return false;
    }





    // Deal with slideshows.
    $slideshow_blocks = [];
    $index = 1;
    $nodes = $xpath->query("//div[contains(@class, 'node-photo-slideshow')]");
    foreach ($nodes as $item) {
      $slideshow_block = BlockContent::create([
        'info' => 'Slideshow for Article ' . $nid,
        'type' => 'slideshow',
        'langcode' => 'en',
      ]);
      $slideshow_block->save();

      foreach ($item->getElementsByTagName('img') as $img) {
        $src = $img->getAttribute('src');
        $src = str_replace('styles/large_aspect/public/', '', $src);
        $file_name = explode("/", $src);
        $file_name = end($file_name);
        $file_name = explode("?", $file_name);
        $file_name = $file_name[0];

        // Check if image already exists.
        $file = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['filename' => $file_name]);

        if ($file) {
          // Get existing Media entity.
          $fileId = array_shift($file)->id();
          $media = \Drupal::entityTypeManager()
            ->getStorage('media')
            ->loadByProperties(['field_media_image' => $fileId]);
          $media = reset($media);
        }
        else {
          // Download the file and create a new Media entity.

          // Alt text.
          $alt = $img->getAttribute('alt');
          $alt = substr($alt, 0, 500);
          // Credit.
          $credit = '';
          $credit_nodes = $xpath->query("//small[contains(@class, 'dcf-txt-xs')]/text()", $img->parentNode->parentNode->parentNode->parentNode->parentNode->parentNode->parentNode);
          if ($credit_nodes->length) {
            $credit = trim($dom->saveHTML($credit_nodes->item(0)));
          }

          $file_data = file_get_contents($src);
          $file = \Drupal::service('file.repository')
            ->writeData($file_data, 'public://media/images/' . $file_name, FileSystemInterface::EXISTS_REPLACE);
          $media = Media::create([
            'bundle' => 'image',
            'uid' => \Drupal::currentUser()->id(),
            'field_media_image' => [
              'target_id' => $file->id(),
              'alt' => $alt,
            ],
            'field_media_image_credit' => [['value' => $credit]],
          ]);
          $media->setName($file_name)
            ->setPublished(TRUE)
            ->save();
        }

        // Cutline.
        $cutline = '';
        $cutline_nodes = $xpath->query("figcaption//text()", $img->parentNode->parentNode->parentNode->parentNode->parentNode->parentNode->parentNode);
        if ($cutline_nodes->length) {
          $cutline = trim($dom->saveHTML($cutline_nodes->item(0)));
        }

        $paragraph = Paragraph::create([
          'type' => 'paragraph_media',
          'field_p_media_cutline' => ['value' => $cutline],
          'field_p_media' => ['target_id' => $media->id()],
        ]);
        $paragraph->save();
        $slideshow_block->field_slideshow_photos->appendItem($paragraph);
        //$slideshow_block->field_slideshow_photos->appendItem(['target_id' => $paragraph->id()]);
        $slideshow_block->save();
      }

      $slideshow_blocks[] = $slideshow_block;

      $slideshow_insert = $dom->createTextNode('[unl:b:' . $index . ']');
      $item->parentNode->replaceChild($slideshow_insert, $item);
      $index++;
    }





    // Deal with images and videos embedded in the body.
    foreach(['entity', 'media-item'] as $mediawrapperclass) {
      $nodes = $xpath->query("//div[contains(@class, 'field-name-body')]/div[contains(@class, $mediawrapperclass)]");
      foreach ($nodes as $item) {
        foreach ($item->getElementsByTagName('img') as $img) {
          if (strpos($img->parentNode->getAttribute('class'), 'metaimage') !== FALSE) {
            $src = $img->getAttribute('src');
            $src = str_replace('styles/meta/public/', '', $src);
            $file_name = explode("/", $src);
            $file_name = end($file_name);
            $file_name = explode("?", $file_name);
            $file_name = $file_name[0];

            // Check if image already exists.
            $file = \Drupal::entityTypeManager()
              ->getStorage('file')
              ->loadByProperties(['filename' => $file_name]);

            if ($file) {
              // Get existing Media entity.
              $fileId = array_shift($file)->id();
              $media = \Drupal::entityTypeManager()
                ->getStorage('media')
                ->loadByProperties(['field_media_image' => $fileId]);
              $media = reset($media);
            }
            else {
              // Download the file and create a new Media entity.

              // Alt text.
              $alt = $img->getAttribute('alt');
              $alt = substr($alt, 0, 500);
              // Credit.
              $credit = '';
              $credit_nodes = $xpath->query("div[contains(@class, 'field-name-field-credit')]//text()", $img->parentNode->parentNode);
              if ($credit_nodes->length) {
                $credit = trim($dom->saveHTML($credit_nodes->item(0)));
              }

              $file_data = file_get_contents($src);
              $file = \Drupal::service('file.repository')
                ->writeData($file_data, 'public://media/images/' . $file_name, FileSystemInterface::EXISTS_REPLACE);
              $media = Media::create([
                'bundle' => 'image',
                'uid' => \Drupal::currentUser()->id(),
                'field_media_image' => [
                  'target_id' => $file->id(),
                  'alt' => $alt,
                ],
                'field_media_image_credit' => [['value' => $credit]],
              ]);
              $media->setName($file_name)
                ->setPublished(TRUE)
                ->save();
            }

            // Cutline.
            $cutline = '';
            $cutline_nodes = $xpath->query("div[contains(@class, 'field-name-field-cutline')]//text()", $img->parentNode->parentNode->parentNode);
            if ($cutline_nodes->length) {
              $cutline = trim($dom->saveHTML($cutline_nodes->item(0)));
            }

            // Alignment.
            $classes = $img->parentNode->parentNode->parentNode->parentNode->parentNode->getAttribute('class');
            if (strpos($classes, 'float-') !== FALSE) {
              if (strpos($classes, 'float-left') !== FALSE) {
                $data_align = 'left';
              }
              else {
                $data_align = 'right';
              }
            }
            else {
              $data_align = 'center';
            }

            // Create a new drupal-media DOM element.
            $drupal_media = $dom->createElement('drupal-media');
            $drupal_media->setAttribute('data-entity-type', 'media');
            $drupal_media->setAttribute('data-entity-uuid', $media->uuid());
            $drupal_media->setAttribute('data-align', $data_align);
            $drupal_media->setAttribute('data-caption', $cutline);

            // Replace the imported field collection HTML with the new drupal-media element.
            $item->parentNode->replaceChild($drupal_media, $item);
          }
          elseif (strpos($img->parentNode->getAttribute('class'), 'metavideo') !== FALSE) {
            $src = $img->getAttribute('src');
            $videoid = explode("/", $src);
            $videoid = end($videoid);
            $videoid = explode(".jpg?", $videoid);
            $videoid = $videoid[0];

            if (str_contains($src, 'mediahub')) {
              $video_url = 'https://mediahub.unl.edu/media/' . $videoid;
            }
            elseif (str_contains($src, 'youtube')) {
              $video_url = 'https://www.youtube.com/watch?v=' . $videoid;
            }
            elseif (str_contains($src, 'vimeo')) {
              $video_url = 'https://vimeo.com/' . $videoid;
            }

            $media = \Drupal::entityTypeManager()
              ->getStorage('media')
              ->loadByProperties(['field_media_oembed_video' => $video_url]);
            $media = reset($media);

            if (!$media) {
              $media = Media::create([
                'bundle' => 'remote_video',
                'uid' => \Drupal::currentUser()->id(),
                'field_media_oembed_video' => [['value' => $video_url]]
              ]);
              $media->setName($video_url)
                ->setPublished(TRUE)
                ->save();
            }

            // Cutline.
            $cutline = '';
            $cutline_nodes = $xpath->query("div[contains(@class, 'field-name-field-cutline')]//text()", $img->parentNode->parentNode->parentNode->parentNode->parentNode);
            if ($cutline_nodes->length) {
              $cutline = trim($dom->saveHTML($cutline_nodes->item(0)));
            }

            // Create a new drupal-media DOM element.
            $drupal_media = $dom->createElement('drupal-media');
            $drupal_media->setAttribute('data-entity-type', 'media');
            $drupal_media->setAttribute('data-entity-uuid', $media->uuid());
            $drupal_media->setAttribute('data-align', 'center');
            $drupal_media->setAttribute('data-caption', $cutline);

            // Replace the imported field collection HTML with the new drupal-media element.
            $item->parentNode->replaceChild($drupal_media, $item);
          }
        }
      }
    }




    // Deal with Media in the sidebar.
    $nodes = $xpath->query("//div[contains(@class, 'dcf-col-25%-end@md')]//div[contains(@class, 'entity')]");
    foreach ($nodes as $item) {
      foreach ($item->getElementsByTagName('img') as $img) {
        $src = $img->getAttribute('src');
        $src = str_replace('styles/meta/public/', '', $src);
        $file_name = explode("/", $src);
        $file_name = end($file_name);
        $file_name = explode("?", $file_name);
        $file_name = $file_name[0];

        // Check if image already exists.
        $file = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['filename' => $file_name]);

        if ($file) {
          // Get existing Media entity.
          $fileId = array_shift($file)->id();
          $media = \Drupal::entityTypeManager()
            ->getStorage('media')
            ->loadByProperties(['field_media_image' => $fileId]);
          $media = reset($media);
        }
        else {
          // Download the file and create a new Media entity.

          // Alt text.
          $alt = $img->getAttribute('alt');
          $alt = substr($alt, 0, 500);
          // Credit.
          $credit = '';
          $credit_nodes = $xpath->query("div[contains(@class, 'field-name-field-credit')]//text()", $img->parentNode->parentNode);
          if ($credit_nodes->length) {
            $credit = trim($dom->saveHTML($credit_nodes->item(0)));
          }

          $file_data = file_get_contents($src);
          $file = \Drupal::service('file.repository')
            ->writeData($file_data, 'public://media/images/' . $file_name, FileSystemInterface::EXISTS_REPLACE);
          $media = Media::create([
            'bundle' => 'image',
            'uid' => \Drupal::currentUser()->id(),
            'field_media_image' => [
              'target_id' => $file->id(),
              'alt' => $alt,
            ],
            'field_media_image_credit' => [['value' => $credit]],
          ]);
          $media->setName($file_name)
            ->setPublished(TRUE)
            ->save();
        }

        // Cutline.
        $cutline = '';
        $cutline_nodes = $xpath->query("div[contains(@class, 'field-name-field-cutline')]//text()", $img->parentNode->parentNode->parentNode);
        if ($cutline_nodes->length) {
          $cutline = trim($dom->saveHTML($cutline_nodes->item(0)));
        }

        // Create a new drupal-media DOM element.
        $drupal_media = $dom->createElement('drupal-media');
        $drupal_media->setAttribute('data-entity-type', 'media');
        $drupal_media->setAttribute('data-entity-uuid', $media->uuid());
        $drupal_media->setAttribute('data-align', 'center');
        $drupal_media->setAttribute('data-caption', $cutline);

        // Append this image to the bottom of the body field.
        $bodyNode->appendChild($drupal_media);
      }
    }




    // Create the Body html.
    $body = implode(array_map([$bodyNode->ownerDocument, "saveHTML"],
      iterator_to_array($bodyNode->childNodes)));



    // Article Hero if it is an image.
    $lead_image_block = null;
    $nodes = $xpath->query("//div[contains(@class, 'article-hero')]//img");
    if ($nodes->length > 0) {
      // Image src and file name.
      $src = $nodes->item(0)->getAttribute('src');
      $src = str_replace('styles/large_aspect/public/', '', $src);
      $file_name = explode("/", $src);
      $file_name = end($file_name);
      $file_name = explode("?", $file_name);
      $file_name = $file_name[0];

      // Check if image already exists.
      $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['filename' => $file_name]);

      if ($file) {
        // Get existing Media entity.
        $fileId = array_shift($file)->id();
        $media = \Drupal::entityTypeManager()
          ->getStorage('media')
          ->loadByProperties(['field_media_image' => $fileId]);
        $media = reset($media);
      }
      else {
        // Download the file and create a new Media entity.

        // Alt text.
        $alt = $nodes->item(0)->getAttribute('alt');
        $alt = substr($alt, 0, 500);
        // Credit.
        $credit = '';
        $credit_nodes = $xpath->query("//div[contains(@class, 'field-name-field-credit')]//text()");
        if ($credit_nodes->length) {
          $credit = trim($dom->saveHTML($credit_nodes->item(0)));
        }

        $file_data = file_get_contents($src);
        $file = \Drupal::service('file.repository')
          ->writeData($file_data, 'public://media/images/'.$file_name, FileSystemInterface::EXISTS_REPLACE);
        $media = Media::create([
          'bundle' => 'image',
          'uid' => \Drupal::currentUser()->id(),
          'field_media_image' => [
            'target_id' => $file->id(),
            'alt' => $alt,
          ],
          'field_media_image_credit' => [['value' => $credit]],
        ]);
        $media->setName($file_name)
          ->setPublished(TRUE)
          ->save();
      }

      // Cutline.
      $cutline = '';
      $nodes = $xpath->query("//div[contains(@class, 'field-name-field-cutline')]//text()");
      if ($nodes->length) {
        $cutline = trim($dom->saveHTML($nodes->item(0)));
      }

      $lead_image_block = BlockContent::create([
        'info' => 'Lead image for Article ' . $nid,
        'type' => 'parallax_image',
        'langcode' => 'en',
        'field_title_placement' => 'Basic',
        'field_cutline' => [['value' => $cutline]],
        'field_image' => $media->id(),
      ]);
      $lead_image_block->save();
    }



    // Article Hero if it is a video.
    $nodes = $xpath->query("//div[contains(@class, 'article-hero')]//iframe");
    if ($nodes->length > 0) {
      $src = $nodes->item(0)->getAttribute('src');
      $videoid = explode("/", $src);
      $videoid = end($videoid);
      $videoid = explode("?", $videoid);
      $videoid = $videoid[0];

      if (str_contains($src, 'mediahub')) {
        $video_url = 'https://mediahub.unl.edu/media/' . $videoid;
      }
      elseif (str_contains($src, 'youtube')) {
        $video_url = 'https://www.youtube.com/watch?v=' . $videoid;
      }
      elseif (str_contains($src, 'vimeo')) {
        $video_url = 'https://vimeo.com/' . $videoid;
      }

      $media = \Drupal::entityTypeManager()
        ->getStorage('media')
        ->loadByProperties(['field_media_oembed_video' => $video_url]);
      $media = reset($media);

      if (!$media) {
        $media = Media::create([
          'bundle' => 'remote_video',
          'uid' => \Drupal::currentUser()->id(),
          'field_media_oembed_video' => [['value' => $video_url]]
        ]);
        $media->setName($video_url)
          ->setPublished(TRUE)
          ->save();
      }

      // Cutline.
      $cutline = '';
      $nodes = $xpath->query("//div[contains(@class, 'field-name-field-cutline')]//text()");
      if ($nodes->length) {
        $cutline = trim($dom->saveHTML($nodes->item(0)));
      }

      $lead_image_block = BlockContent::create([
        'info' => 'Lead media for Article ' . $nid,
        'type' => 'parallax_image',
        'langcode' => 'en',
        'field_title_placement' => 'Basic',
        'field_cutline' => [['value' => $cutline]],
        'field_image' => $media->id(),
      ]);
      $lead_image_block->save();
    }




    // High resolution photos.
    $high_res_photos = [];
    $nodes = $xpath->query("//div[contains(@class, 'field-name-field-high-resolution-photos')]");
    foreach ($nodes as $item) {
      foreach ($item->getElementsByTagName('img') as $img) {
        $src = $img->getAttribute('src');
        $src = str_replace('styles/meta/public/', '', $src);
        $file_name = explode("/", $src);
        $file_name = end($file_name);
        $file_name = explode("?", $file_name);
        $file_name = $file_name[0];

        // Check if image already exists.
        $file = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['filename' => $file_name]);

        if ($file) {
          // Get existing Media entity.
          $fileId = array_shift($file)->id();
          $media = \Drupal::entityTypeManager()
            ->getStorage('media')
            ->loadByProperties(['field_media_image' => $fileId]);
          $media = reset($media);
        }
        else {
          // Download the file and create a new Media entity.

          // Alt text.
          $alt = $img->getAttribute('alt');
          $alt = substr($alt, 0, 500);

          $file_data = file_get_contents($src);
          $file = \Drupal::service('file.repository')
            ->writeData($file_data, 'public://media/images/' . $file_name, FileSystemInterface::EXISTS_REPLACE);
          $media = Media::create([
            'bundle' => 'image',
            'uid' => \Drupal::currentUser()->id(),
            'field_media_image' => [
              'target_id' => $file->id(),
              'alt' => $alt,
            ],
          ]);
          $media->setName($file_name)
            ->setPublished(TRUE)
            ->save();
        }

        $high_res_photos[] = $media;
      }
    }




    // Tags.
    $terms = [];
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($tags as $tag_name) {
      $found_terms = $storage->loadByProperties([
        'name' => $tag_name,
        'vid' => 'tags',
      ]);
      $term = reset($found_terms);
      if (!$term) {
        $term = Term::create([
          'name' => $tag_name,
          'vid' => 'tags',
        ]);
        $term->save();
      }
      $terms[] = $term->id();
    }


    // Section.
    $section_terms = [];
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($sections as $section) {
      $found_terms = $storage->loadByProperties([
        'name' => $section,
        'vid' => 'news_section',
      ]);
      $term = reset($found_terms);
      if ($term) {
        $section_terms[] = $term->id();
      }
    }


    // Related links.
    $related_links = [];
    $nodes = $xpath->query("//div[contains(@class, 'field-name-field-related-links')]");
    foreach ($nodes as $item) {
      foreach ($item->getElementsByTagName('a') as $a) {
        $related_links[] = [
          'href' => $a->getAttribute('href'),
          'title' => $a->textContent,
        ];
      }
    }



    // Home page section.
    $field_article_priority = '_none';
    switch ($home_page_section) {
      case 'Lead Story':
        $field_article_priority = 'lead';
        break;
      case 'Headline Story':
        $field_article_priority = 'headline';
        break;
      case 'Featured Event':
        $field_article_priority = 'featured';
        break;
      case 'Long Running Featured Story':
        $field_article_priority = 'long';
        break;
    }





    // Create a node.
    $node = Node::create([
      'type' => 'article',
      'nid' => $nid,
      'uuid' => $uuid,
      'changed' => $changed,
      'created' => $created,
      'status' => 1,
      'path' => [
        'alias' => str_replace('/newsrooms/today', '', $alias),
        'pathauto' => PathautoState::SKIP,
      ],
      'title' => $title,
      'body' => ['summary' => $summary, 'value' => $body, 'format' => 'basic_html'],
      'field_subtitle' => $subtitle,
      'field_article_priority' => $field_article_priority,
    ]);
    if (!empty($lead_image_block)) {
      $node->field_article_lead_image->appendItem(['target_id' => $lead_image_block->id()]);
    }
    if (!empty($written_by_term)) {
      $node->field_written_by->appendItem(['target_id' => $written_by_term->id()]);
    }
    foreach ($high_res_photos as $high_res_photo) {
      $node->field_high_resolution_photo->appendItem(['target_id' => $high_res_photo->id()]);
    }
    foreach ($terms as $term) {
      $node->field_tags->appendItem(['target_id' => $term]);
    }
    foreach ($section_terms as $term) {
      $node->field_section->appendItem(['target_id' => $term]);
    }
    foreach ($news_release_contacts as $contact) {
      $node->field_article_release_contacts->appendItem(['target_id' => $contact]);
    }
    foreach ($related_links as $related_link) {
      $node->field_article_related_links->appendItem(['uri' => $related_link['href'], 'title' => $related_link['title']]);
    }
    foreach ($slideshow_blocks as $slideshow) {
      $node->field_article_blocks->appendItem(['target_id' => $slideshow->id()]);
    }
    $node->save();

    $context['results'][] = $url;
    $context['message'] = t('Imported @title', array('@title' => $url));
  }

}
