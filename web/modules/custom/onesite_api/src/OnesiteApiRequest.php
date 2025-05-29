<?php

namespace Drupal\onesite_api;


/**
 * Defines an API request.
 */
class OnesiteApiRequest {

  /**
   * The domain the request came from.
   *
   * @var string
   */
  protected $host;

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
    $request = \Drupal::request();

    $this->host = $request->getHost();

    $params = $request->query->all();
    foreach ($params as $key => $value) {
      $value = trim($value);
      $this->queryParameters[$key] = filter_var($value, FILTER_SANITIZE_STRING);
    }
  }

  /**
   * Gets the domain.
   *
   * @return string
   */
  public function getHost() {
    return $this->host;
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
