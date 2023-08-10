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

    $url = 'https://localhost.unl.edu/drupal-10-migration-articles.xml';
    $request = \Drupal::httpClient()->get($url);
    $body = $request->getBody();
    $nodes_to_import = simplexml_load_string($body);

    $batch = [
      'title' => t('Importing animals'),
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
      $tags = (array)$item->tag;

      $batch['operations'][] = [
        ['\Drupal\unl_news_migrate\Form\ImportForm', 'importPage'], [$url, $nid, $changed, $created, $uuid, $alias, $tags]
      ];
    }

    batch_set($batch);
    \Drupal::messenger()->addMessage('Imported ' . count($nodes_to_import) . ' animals!');

    $form_state->setRebuild(TRUE);
  }

  /**
   * Imports an article node.
   */
  public static function importPage($url, $nid, $changed, $created, $uuid, $alias, $tags, &$context) {
    $request = \Drupal::httpClient()->get($url);
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

    // Written by.
    $written_by_term = null;
    $nodes = $xpath->query("//header/div[contains(@class, 'field-name-field-written-by')]/a//text()");
    if ($nodes->length > 0) {
      $written_by = $dom->saveHTML($nodes->item(0));
      $written_by_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $written_by, 'vid' => 'written_by']);
      $written_by_term = reset($written_by_term);
      if (!$written_by_term) {
        $written_by_term = Term::create(['name' => $written_by, 'vid' => 'written_by'])->save();
      }
    }

    // Body field.
    $nodes = $xpath->query("//div[contains(@class, 'field-name-body')]");
    if (!$bodyNode = $nodes->item(0)) {
      return false;
    }

    // Deal with images embedded in the body.
    $nodes = $xpath->query("//div[contains(@class, 'field-name-body')]/div[contains(@class, 'entity')]");
    foreach($nodes as $item) {
      foreach($item->getElementsByTagName('img') as $img) {
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
          // Credit.
          $nodes = $xpath->query("//div[contains(@class, 'field-name-field-credit')]//text()", $item);
          $credit = $dom->saveHTML($nodes->item(0));

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
          $media->setName($name)
            ->setPublished(TRUE)
            ->save();
        }

        // Cutline.
        $nodes = $xpath->query("//div[contains(@class, 'field-name-field-cutline')]//text()", $item);
        $cutline = $dom->saveHTML($nodes->item(0));

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



    // Create the Body html.
    $body = implode(array_map([$bodyNode->ownerDocument, "saveHTML"],
      iterator_to_array($bodyNode->childNodes)));



    // Lead image.
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
        $alt = $nodes->item(0)->getAttribute("alt");
        // Credit.
        $nodes = $xpath->query("//div[contains(@class, 'field-name-field-credit')]//text()");
        $credit = $dom->saveHTML($nodes->item(0));

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
        $media->setName($name)
          ->setPublished(TRUE)
          ->save();
      }

      // Cutline.
      $nodes = $xpath->query("//div[contains(@class, 'field-name-field-cutline')]//text()");
      $cutline = $dom->saveHTML($nodes->item(0));

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

    // Create a node.
    $node = Node::create([
      'type' => 'article',
      'nid' => $nid,
      'uuid' => $uuid,
      'changed' => $changed,
      'created' => $created,
      'status' => 1,
      'path' => [
        'alias' => $alias,
        'pathauto' => PathautoState::SKIP,
      ],
      'title' => $title,
      'body' => [['value' => $body, 'format' => 'basic_html']],
      'field_article_lead_image' => $lead_image_block->id(),
      'field_written_by' => [['target_id' => $written_by_term->id()]],
    ]);
    foreach ($terms as $term) {
      $node->field_tags->appendItem(['target_id' => $term]);
    }
    $node->save();

    $context['results'][] = $url;
    $context['message'] = t('Created @title', array('@title' => $url));
  }

}
