<?php

namespace Drupal\onesite_api;

/**
 * Defines the Tags endpoint class.
 *
 * Only the 'format' query parameter is accepted.
 */
class OnesiteApiTags extends OnesiteApiBase {

  /**
   * Instantiates an ApiTags object.
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

    if (isset($params['format'])) {
      $this->format = $params['format'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function performQuery() {

    // Build EFQ query to query taxonomic terms.
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid','tags')
      ->sort('name', 'ASC')
      ->accessCheck(FALSE);
    $results = $query->execute();

    // Keep just the entity ids.
    $results = array_values($results);

    if (!is_null($results)) {
      // Load entities.
      $entities = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($results);
    }
    else {
      $entities = [];
    }

    $return_entities = [];
    foreach ($entities as $entity) {
      $processed_entity = [];
      // Tag id (tid) is used as unique identifier.
      $processed_entity['id'] = $entity->tid->value;
      $processed_entity['name'] = strip_tags($entity->name->value);

      $return_entities[] = $processed_entity;
    }
    $count = count($return_entities);

    return new OnesiteApiResults($return_entities, 1, $count, $count);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDataForXml($data) {
    if (!isset($data['data'])) {
      return $data;
    }

    // Rename 'data' to 'articles'.
    $data['tags'] = $data['data'];
    unset($data['data']);

    // Loop through tags.
    foreach ($data['tags'] as $item_key => $item) {
      // Replace tag key with '__custom' key.
      $data['tags']['__custom:tag:' . $item_key] = $item;
      unset($data['tags'][$item_key]);
    }

    return $data;
  }

}
