<?php

/**
 * Defines an API request.
 */
class OnesiteApiRequest {

  /**
   * An array of query string parameters.
   *
   * @var array
   */
  protected $queryParameters;

  /**
   * Creates a new OnesiteApiRequest object.
   */
  public function __construct() {
    foreach (drupal_get_query_parameters() as $key => $value) {
      $value = trim($value);
      $this->queryParameters[$key] = filter_var($value, FILTER_SANITIZE_STRING);
    }
  }

  /**
   * Gets the query string parameters.
   *
   * @return array
   *   An array of query string parameters.
   */
  public function getParameters() {
    return $this->queryParameters;
  }

  /**
   * Gets a specific query string parameter.
   *
   * @return string
   *   The values of the requested query string parameter.
   */
  public function getParameter($key) {
    return $this->queryParameters[$key] ?? '';
  }

}
