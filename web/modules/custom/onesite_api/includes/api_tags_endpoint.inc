<?php

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
    // Vocabularies to query.
    $vocabularies = ['tags', 'free_tags'];

    // Build EFQ query to retrieve entity IDs for vocabularies.
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'taxonomy_vocabulary');
    $query->propertyCondition('machine_name', $vocabularies, 'IN');
    $results = $query->execute();
    $results = array_shift($results);
    $vids = array_keys($results);

    // Build EFQ query to query taxonomic terms.
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'taxonomy_term');
    $query->entityCondition('vid', $vids, 'IN');
    $query->propertyOrderBy('name', 'ASC');
    $results = $query->execute();

    // Keep just the entity ids.
    $results = array_shift($results);

    if (!is_null($results)) {
      // Load entities.
      $entities = entity_load('taxonomy_term', array_keys($results));
    }
    else {
      $entities = [];
    }

    $return_entities = [];
    foreach ($entities as $entity) {
      $processed_entity = [];
      // Tag id (tid) is used as unique identifier.
      $processed_entity['id'] = $entity->tid;
      $processed_entity['name'] = strip_tags($entity->name);

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
