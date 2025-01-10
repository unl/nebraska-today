<?php

namespace Drupal\unl_ianrnews_migrate\Form;

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
 * @ingroup unl_ianrnews_migrate
 */
class ImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'unl_ianrnews_migrate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#prefix'] = '<p>IANR News 7 to 10 migrator.</p>';

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
    $base_url = 'https://unlcms.unl.edu/';
    $base_url = trim($base_url, '/') . '/';

    $url = 'https://unlcms.unl.edu/educational-media/ianrnews/drupal-10-migration-articles.xml?cachebuster1';
    //$url = 'https://localhost.unl.edu/drupal-10-migration-articles.xml';
    $request = \Drupal::httpClient()->get($url);
    $body = $request->getBody();
    $nodes_to_import = simplexml_load_string($body);

    $batch = [
      'title' => t('Importing articles from ianrnews.unl.edu'),
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
      $alias = substr($url, strlen($base_url)-1);
      $summary = (string)$item->summary;
      $tags = (array)$item->tag;

      $batch['operations'][] = [
        ['\Drupal\unl_ianrnews_migrate\Form\ImportForm', 'importPage'], [$url, $nid, $changed, $created, $alias, $summary, $tags]
      ];
    }

    batch_set($batch);
    \Drupal::messenger()->addMessage('Imported ' . count($nodes_to_import) . ' articles!');

    $form_state->setRebuild(TRUE);
  }

  /**
   * Imports an article node.
   */
  public static function importPage($url, $nid, $changed, $created, $alias, $summary, $tags, &$context) {

    // Check if node alrady exists (from a failed/interrupted attempt) and skip if it does.
    $values = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('nid', 1000000+(int)$nid)->execute();
    $node_exists = !empty($values);
    if ($node_exists) {
      return;
    }

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

    // Title.
    $title = $url;
    $nodes = $xpath->query("//header[@id='dcf-page-title']/h1//text()");
    if ($nodes->length > 0) {
      $title = $dom->saveHTML($nodes->item(0));
    }

    // Written by/Byline.
    $written_by_term = null;
    $nodes = $xpath->query("//div[contains(@class, 'field-name-field-byline')]/span//text()");
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


    // TODO: deal with images in body


    // Create the Body html.
    $body = implode(array_map([$bodyNode->ownerDocument, "saveHTML"],
      iterator_to_array($bodyNode->childNodes)));



    // Article Hero if it is an image.
    $lead_image_block = null;
    $nodes = $xpath->query("//div[contains(@class, 'article-hero')]//img");
    if ($nodes->length > 0) {
      // Image src and file name.
      $src = $nodes->item(0)->getAttribute('src');
      $src = str_replace('styles/article_hero_image_-_16x9/public/', '', $src);
      $file_name = explode("/", $src);
      $file_name = end($file_name);
      $file_name = explode("?", $file_name);
      $file_name = 'ianrnews_' . $file_name[0];

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
        ]);
        $media->setName($file_name)
          ->setPublished(TRUE)
          ->save();
      }

      // Cutline.
      $cutline = '';
      $nodes = $xpath->query("//div[contains(@class, 'field-name-field-image-caption')]//text()");
      if ($nodes->length) {
        foreach ($nodes as $key => $node) {
          if ($key !== 0) {
            $cutline .= ' – ';
          }
          $cutline .= trim($dom->saveHTML($nodes->item($key)));
        }
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




    // Create a node.
    $node = Node::create([
      'type' => 'article',
      'nid' => 1000000+(int)$nid,
      'changed' => $changed,
      'created' => $created,
      'status' => 1,
      'path' => [
        'alias' => str_replace('/educational-media/ianrnews', '', $alias),
        'pathauto' => PathautoState::SKIP,
      ],
      'title' => $title,
      'body' => ['summary' => $summary, 'value' => $body, 'format' => 'basic_html'],
      'field_domain_access' => ['target_id' => 'ianrnews_unl_edu'],
    ]);
    if (!empty($media)) {
      $node->field_article_lead_media = $media;
      $node->field_article_lead_cutline = $cutline;
    }
    if (!empty($written_by_term)) {
      $node->field_written_by->appendItem(['target_id' => $written_by_term->id()]);
    }
    foreach ($terms as $term) {
      $node->field_tags->appendItem(['target_id' => $term]);
    }
    $node->save();

    $context['results'][] = $url;
    $context['message'] = t('Imported @title', array('@title' => $url));
  }

}
