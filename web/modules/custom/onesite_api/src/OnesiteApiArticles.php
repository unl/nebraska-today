<?php

namespace Drupal\onesite_api;

use Drupal\node\NodeInterface;

/**
 * Defines the Articles endpoint class.
 */
class OnesiteApiArticles extends OnesiteApiBase {

  /**
   * An array of requested section IDs.
   *
   * @var array
   */
  protected $sectionIds = [];

  /**
   * An array of requested tag IDs.
   *
   * @var array
   */
  protected $tagIds = [];

  /**
   * An array of requested author IDs.
   *
   * @var array
   */
  protected $authorIds = [];

  /**
   * How news releases should be included/excluded.
   *
   * NULL - return can include both news release and non-new release content.
   * 1 - only news releases should be included in return.
   * 0 - news releases should be excluded from return.
   *
   * @var mixed
   */
  protected $newsRelease = NULL;

  /**
   * Instantiates an ApiArticles object.
   */
  public function __construct() {
    parent::__construct();
    $this->retrieveQueryStringParameters();
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveQueryStringParameters() {
    $request = new OnesiteApiRequest();
    $params = $request->getParameters();

    if (isset($params['sectionIds'])) {
      $this->sectionIds = $this->convertQueryStringParameterToArray($params['sectionIds']);
    }
    if (isset($params['tagIds'])) {
      $this->tagIds = $this->convertQueryStringParameterToArray($params['tagIds']);
    }
    if (isset($params['authorIds'])) {
      $this->authorIds = $this->convertQueryStringParameterToArray($params['authorIds']);
    }
    if (isset($params['newsRelease'])) {
      $this->newsRelease = $params['newsRelease'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateParameters() {
    parent::validateParameters();

    // Validate section IDs.
    foreach ($this->sectionIds as $id) {
      if (!is_numeric($id)) {
        $this->setError('400', 'The "sectionIds" parameter is invalid. All IDs must be numeric.');
      }
    }

    // Validate tag IDs.
    foreach ($this->tagIds as $id) {
      if (!is_numeric($id)) {
        $this->setError('400', 'The "tagIds" parameter is invalid. All IDs must be numeric.');
      }
    }

    // Validate author IDs.
    foreach ($this->authorIds as $id) {
      if (!is_numeric($id)) {
        $this->setError('400', 'The "authorIds" parameter is invalid. All IDs must be numeric.');
      }
    }

    // Validate news release.
    if (!is_null($this->newsRelease)) {
      if ($this->newsRelease !== '0' && $this->newsRelease !== '1') {
        $this->setError('400', 'The "newsRelease" parameter is invalid. Accepted values: "0" and "1".');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function performQuery() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'article')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC')
      ->accessCheck(FALSE);

    if ($this->tagIds) {
      $query->condition('field_tags', $this->tagIds, 'IN');
    }
//    if ($this->sectionIds) {
//      $query->condition('field_news_section', 'tid', $this->sectionIds, 'IN');
//    }
//    if ($this->authorIds) {
//      $query->condition('field_written_by', 'tid', $this->authorIds, 'IN');
//    }
//    if ($this->newsRelease == 1) {
//      $query->condition('field_news_release_contacts', 'target_id', NULL, 'IS NOT NULL');
//    }
//    elseif ($this->newsRelease !== null) {
//      // In D8, the database API has an exists() method.
//      $query->addTag('onesite_api_articles_news_release');
//    }

    // Determine the total number of records.
    $count_query = clone($query);
    $count_query->count();
    $count = $count_query->execute();

    // Execute query on desired subset of results.
    // The page query parameter is already handled by EFQ, so there's nothing
    // to do here.
    $query->pager($this->pageCount);
    $results = $query->execute();

    // Keep just the entity ids.
    $results = array_shift($results);

    if (!is_null($results)) {
      // Load entities.
      $entities = entity_load('node', array_keys($results));
    }
    else {
      $entities = [];
    }

    $return_entities = [];
    foreach ($entities as $entity) {
      $processed_entity = [];
      // Node ID is used as unique identifier.
      $processed_entity['id'] = $entity->nid;

      // Date is provided in ISO 8601 format.
      $processed_entity['pubDate'] = date("c", $entity->created);

      // Canonical URL is provided as an absolute URL.
      $processed_entity['canonicalUrl'] = url('node/' . $entity->nid, ['absolute' => TRUE, 'https' => TRUE]);

      // Title is required.
      $processed_entity['title'] = strip_tags($entity->title);

      // SubTitle is optional.
      if (isset($entity->field_subtitle[LANGUAGE_NONE][0]['value'])) {
        $processed_entity['subTitle'] = strip_tags($entity->field_subtitle[LANGUAGE_NONE][0]['value']);
      }

      // Author is optional.
      if (isset($entity->field_written_by[LANGUAGE_NONE][0]['tid'])) {
        $author_id = $entity->field_written_by[LANGUAGE_NONE][0]['tid'];
        $processed_entity['authorId'] = $author_id;

        $author_entity = entity_load('taxonomy_term', [$author_id]);
        $author_name = array_shift($author_entity)->name;
        $processed_entity['authorName'] = strip_tags($author_name);
      }

      // Sections are optional.
      if (isset($entity->field_news_section[LANGUAGE_NONE])) {
        foreach ($entity->field_news_section[LANGUAGE_NONE] as $section) {
          $section_entity = entity_load('taxonomy_term', [$section['tid']]);
          $section_name = array_shift($section_entity)->name;
          $processed_entity['sections'][] = [
            'id' => $section['tid'],
            'label' => strip_tags($section_name),
          ];
        }
      }

      // Tags are optional.
      if (isset($entity->field_tags[LANGUAGE_NONE])) {
        foreach ($entity->field_tags[LANGUAGE_NONE] as $tag) {
          $term_entity = entity_load('taxonomy_term', [$tag['tid']]);
          $term_name = array_shift($term_entity)->name;
          $processed_entity['tags'][] = [
            'id' => $tag['tid'],
            'label' => strip_tags($term_name),
          ];
        }
      }

      // News Release Contacts are optional.
      // 'newsRelease' is determined by whether or not there is at least one
      // News Release Contact.
      if (isset($entity->field_news_release_contacts[LANGUAGE_NONE])) {
        $processed_entity['newsRelease'] = 1;
        foreach ($entity->field_news_release_contacts[LANGUAGE_NONE] as $contact) {
          $contact_node = array_shift(entity_load('node', [$contact['target_id']]));
          $processed_entity['newsReleaseContacts'][] = [
            'name' => strip_tags($contact_node->title),
            'jobTitle' => strip_tags($contact_node->field_job_title[LANGUAGE_NONE][0]['value']),
            'phone' => strip_tags($contact_node->field_phone[LANGUAGE_NONE][0]['value']),
            'email' => strip_tags($contact_node->field_email[LANGUAGE_NONE][0]['value']),
            'websiteUrl' => strip_tags($contact_node->field_website[LANGUAGE_NONE][0]['url']),
          ];
        }
      }
      else {
        $processed_entity['newsRelease'] = 0;
      }

      // News Release Photos are optional.
      if (isset($entity->field_high_resolution_photos[LANGUAGE_NONE])) {
        foreach ($entity->field_high_resolution_photos[LANGUAGE_NONE] as $key => $photo) {
          $processed_entity['newsReleasePhotos'][$key] = [
            'url' => file_create_url($photo['uri']),
            'width' => $photo['width'],
            'height' => $photo['height'],
            'mimeType' => $photo['filemime'],
            'size' => $photo['filesize'],
          ];
        }

        // Alt text is optional.
        if (isset($photo['alt'])) {
          $processed_entity['newsReleasePhotos'][$key]['alt'] = strip_tags($photo['alt']);
        }

        // Credit is optional.
        if (isset($entity->field_high_resolution_photos[LANGUAGE_NONE][$key]['field_credit'][LANGUAGE_NONE])) {
          $processed_entity['newsReleasePhotos'][$key]['credit'] = strip_tags($entity->field_high_resolution_photos[LANGUAGE_NONE][$key]['field_credit'][LANGUAGE_NONE][0]['value']);
        }

        // Caption is optional.
        if (isset($entity->field_high_resolution_photos[LANGUAGE_NONE][$key]['field_caption'][LANGUAGE_NONE])) {
          $processed_entity['newsReleasePhotos'][$key]['caption'] = strip_tags($entity->field_high_resolution_photos[LANGUAGE_NONE][$key]['field_caption'][LANGUAGE_NONE][0]['value']);
        }
      }

      // Related Links are optional.
      if (isset($entity->field_related_links[LANGUAGE_NONE])) {
        foreach ($entity->field_related_links[LANGUAGE_NONE] as $link) {
          $processed_entity['relatedLinks'][] = [
            'url' => $link['url'],
            'title' => strip_tags($link['title']),
          ];
        }
      }

      // Article Image is optional.
      //
      // The article hero image is the first field collection item in
      // $entity->field_media_collection. All other items are media
      // that are available for embedding.
      if (isset($entity->field_media_collection[LANGUAGE_NONE][0])) {
        $field_collection_item = entity_load('field_collection_item', [$entity->field_media_collection[LANGUAGE_NONE][0]['value']]);
        $field_collection_item = array_shift($field_collection_item);

        $processed_entity['articleImage'] = [
          'url' => file_create_url($field_collection_item->field_media[LANGUAGE_NONE][0]['uri']),
          'mimeType' => $field_collection_item->field_media[LANGUAGE_NONE][0]['filemime'],
          'size' => $field_collection_item->field_media[LANGUAGE_NONE][0]['filesize'],
        ];

        // Width is optional.
        if (isset($field_collection_item->field_media[LANGUAGE_NONE][0]['width'])) {
          $processed_entity['articleImage']['width'] = $field_collection_item->field_media[LANGUAGE_NONE][0]['width'];
        }

        // Height is optional.
        if (isset($field_collection_item->field_media[LANGUAGE_NONE][0]['height'])) {
          $processed_entity['articleImage']['height'] = $field_collection_item->field_media[LANGUAGE_NONE][0]['height'];
        }

        // Alt text is optional.
        if (isset($field_collection_item->field_media[LANGUAGE_NONE][0]['field_caption'][LANGUAGE_NONE][0]['value'])) {
          $processed_entity['articleImage']['alt'] = strip_tags($field_collection_item->field_media[LANGUAGE_NONE][0]['field_caption'][LANGUAGE_NONE][0]['value']);
        }

        // Credit is optional.
        if (isset($field_collection_item->field_media[LANGUAGE_NONE][0]['field_credit'][LANGUAGE_NONE])) {
          $processed_entity['articleImage']['credit'] = strip_tags($field_collection_item->field_media[LANGUAGE_NONE][0]['field_credit'][LANGUAGE_NONE][0]['value']);
        }

        // Caption is optional.
        if (isset($field_collection_item->field_cutline[LANGUAGE_NONE])) {
          $processed_entity['articleImage']['caption'] = strip_tags($field_collection_item->field_cutline[LANGUAGE_NONE][0]['value']);
        }
      }

      // Body is optional.
      if (isset($entity->body[LANGUAGE_NONE][0]['value'])
        && !empty(isset($entity->body[LANGUAGE_NONE][0]['value']))
      ) {
        $processed_entity['content'] = check_markup($entity->body[LANGUAGE_NONE][0]['value'], $entity->body[LANGUAGE_NONE][0]['format']);
      }

      // Teaser is optional.
      if (isset($entity->body[LANGUAGE_NONE][0]['safe_summary'])
        && !empty($entity->body[LANGUAGE_NONE][0]['safe_summary'])
      ) {
        $processed_entity['teaser'] = $entity->body[LANGUAGE_NONE][0]['safe_summary'];
      }

      $return_entities[] = $processed_entity;
    }

    return new OnesiteApiResults($return_entities, $this->page, $this->pageCount, $count);
  }

  /**
   * Alters queries tagged with 'onesite_api_articles_news_release'.
   *
   * Called by onesite_api_query_onesite_api_articles_news_release_alter().
   *
   * @param QueryAlterableInterface $query
   *   An EFQ query.
   */
  public static function queryAlterNewsRelease(QueryAlterableInterface $query) {
    $query->leftJoin('field_data_field_news_release_contacts', 'n', 'node.nid = n.entity_id');
    $query->isNull('n.field_news_release_contacts_target_id');
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDataForXml($data) {
    if (!isset($data['data'])) {
      return $data;
    }

    // Rename 'data' to 'articles'.
    $data['articles'] = $data['data'];
    unset($data['data']);

    // Loop through articles.
    foreach ($data['articles'] as $item_key => $item) {
      // Loop through sections and replace key with '__custom' key.
      if (isset($item['sections'])) {
        $sections_array = [];
        foreach ($item['sections'] as $section_key => $tag) {
          $sections_array['__custom:section:' . $section_key] = $tag;
        }
        $item['sections'] = $sections_array;
      }

      // Loop through tags and replace key with '__custom' key.
      if (isset($item['tags'])) {
        $tags_array = [];
        foreach ($item['tags'] as $tag_key => $tag) {
          $tags_array['__custom:tag:' . $tag_key] = $tag;
        }
        $item['tags'] = $tags_array;
      }

      // Loop through news release contacts and replace key with '__custom' key.
      if (isset($item['newsReleaseContacts'])) {
        $contacts_array = [];
        foreach ($item['newsReleaseContacts'] as $contact_key => $tag) {
          $contacts_array['__custom:newsReleaseContact:' . $contact_key] = $tag;
        }
        $item['newsReleaseContacts'] = $contacts_array;
      }

      // Loop through news release photos and replace key with '__custom' key.
      if (isset($item['newsReleasePhotos'])) {
        $photos_array = [];
        foreach ($item['newsReleasePhotos'] as $photo_key => $photo) {
          $photos_array['__custom:newsReleasePhoto:' . $photo_key] = $photo;
        }
        $item['newsReleasePhotos'] = $photos_array;
      }

      // Loop through related links and replace key with '__custom' key.
      if (isset($item['relatedLinks'])) {
        $links_array = [];
        foreach ($item['relatedLinks'] as $link_key => $tag) {
          $links_array['__custom:relatedLink:' . $link_key] = $tag;
        }
        $item['relatedLinks'] = $links_array;
      }

      // Wrap content in CDATA wrapper.
      if (isset($item['content'])) {
        $item['content'] = [
          '_cdata' => $item['content'],
        ];
      }

      // Wrap teaser in CDATA wrapper.
      if (isset($item['teaser'])) {
        $item['teaser'] = [
          '_cdata' => $item['teaser'],
        ];
      }

      // Replace article key with '__custom' key.
      $data['articles']['__custom:article:' . $item_key] = $item;
      unset($data['articles'][$item_key]);
    }

    return $data;
  }

}
