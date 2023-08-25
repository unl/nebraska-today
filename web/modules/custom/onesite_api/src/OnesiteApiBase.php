<?php

namespace Drupal\onesite_api;

/**
 * Defines a base class for API endpoints.
 *
 * Endpoints should be plugins in Drupal 8.
 */
abstract class OnesiteApiBase {

  /**
   * The requested return format (either 'xml' or 'json').
   *
   * @var string
   */
  protected $format = 'json';

  /**
   * The page number to pass to EFQ.
   *
   * @var int
   */
  protected $page = 1;

  /**
   * The number of results per page.
   *
   * Max number of results per page is 50.
   *
   * @var int
   */
  protected $pageCount = 50;

  /**
   * Validation errors.
   *
   * @var array
   * @see ::setError()
   */
  protected $errors = [];

  /**
   * Instantiates an ApiBase object.
   */
  public function __construct() {
    self::retrieveQueryStringParameters();
  }

  /**
   * Retrieves the query string parameters and stores them as class properties.
   */
  public function retrieveQueryStringParameters() {
    $request = new OnesiteApiRequest();
    $params = $request->getParameters();

    if (isset($params['format'])) {
      $this->format = $params['format'];
    }
    if (isset($params['page'])) {
      $this->page = $params['page'];
    }
    if (isset($params['pageCount'])) {
      $this->pageCount = $params['pageCount'];
    }
  }

  /**
   * Validates parameters provided by client.
   *
   * If a parameter fails validation, then the implementing method should
   * use the 'setError' method to set an error.
   */
  public function validateParameters() {
    // Validate format.
    $valid_formats = array(
      'xml',
      'json',
    );
    if (!in_array($this->format, $valid_formats)) {
      $this->setError('400', 'The "format" parameter is invalid. Accepted values: "xml" and "json".');
    }

    // Validate page.
    if (!is_numeric($this->page)) {
      $this->setError('400', 'The "page" parameter must be an integer.');
    }

    // Validate page count.
    if (!is_numeric($this->pageCount)
      || $this->pageCount < 1
      || $this->pageCount > 50
    ) {
      $this->setError('400', 'The "pageCount" parameter must be a positive integer between 1 and 50.');
    }
  }

  /**
   * Prepares data to be converted into XML.
   *
   * @param array $data
   *   An array of data to be processed.
   *
   * @return array
   *   An array of processed data.
   */
  public function prepareDataForXml($data) {
    return $data;
  }

  /**
   * Performs entities queries to build an array of data to return to client.
   *
   * @return OnesiteApiResults
   *   An OnesiteApiResults object.
   */
  public function performQuery() {
    return new OnesiteApiResults([], $this->page, $this->pageCount, 0);
  }

  /**
   * Converts a query string parameter to an array.
   *
   * Only the '+' delimiter is supported.
   *
   * @param string $parameter_value
   *   A query string parameter value.
   *
   * @return array
   *   The query string parameter value converted into an array.
   */
  protected function convertQueryStringParameterToArray($parameter_value) {
    // In query string parameter values, the '+' character has semantic meaning
    // and represents a space.
    return explode(' ', $parameter_value);
  }

  /**
   * Gets validation errors.
   *
   * @return array
   *   An array of errors.
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * Sets a validation error.
   *
   * @param int $code
   *   An HTTP status code that corresponds to the error.
   * @param string $message
   *   A message describing the error.
   */
  protected function setError($code, $message) {
    $this->errors[] = [
      'code' => $code,
      'message' => $message,
    ];
  }

  /**
   * Gets the format of the requested return.
   *
   * @return string
   *   The requested return format (either 'xml' or 'json').
   */
  public function getFormat() {
    return $this->format;
  }

}
