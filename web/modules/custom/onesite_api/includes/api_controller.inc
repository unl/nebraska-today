<?php

use Spatie\ArrayToXml\ArrayToXml;

/**
 * Defines an API controller.
 */
class OnesiteApiController {

  /**
   * Returns API results to a client for articles.
   *
   * @param string $api_version
   *   The version of the API being called.
   */
  public static function apiResponseArticles($api_version) {
    if ($api_version == 'v1') {
      drupal_page_is_cacheable(FALSE);

      $response = new OnesiteApiReponse();

      $endpoint = new OnesiteApiArticles();
      $endpoint->validateParameters();

      if ($errors = $endpoint->getErrors()) {
        // Only return first error.
        $error = array_values($errors)[0];
        $prepared_response = self::prepareReturn($error['code'], $error['message'], $endpoint);

        $response->setContentType($prepared_response['http_content_type']);
        $response->setBody($prepared_response['body']);
        $response->send();
      }
      else {
        $results = $endpoint->performQuery();
        $prepared_response = self::prepareReturn('200', 'Success', $endpoint, $results);

        $response->setContentType($prepared_response['http_content_type']);
        $response->setBody($prepared_response['body']);
        $response->send();
      }
    }
    else {
      throw new Exception('Invalid API version');
    }
  }

  /**
   * Returns API results to a client for tags.
   *
   * @param string $api_version
   *   The version of the API being called.
   */
  public static function apiResponseTags($api_version) {
    if ($api_version == 'v1') {
      drupal_page_is_cacheable(FALSE);

      $response = new OnesiteApiReponse();

      $endpoint = new OnesiteApiTags();
      $endpoint->validateParameters();

      if ($errors = $endpoint->getErrors()) {
        // Only return first error.
        $error = array_values($errors)[0];
        $prepared_response = self::prepareReturn($error['code'], $error['message'], $endpoint);

        $response->setContentType($prepared_response['http_content_type']);
        $response->setBody($prepared_response['body']);
        $response->send();
      }
      else {
        $results = $endpoint->performQuery();
        $prepared_response = self::prepareReturn('200', 'Success', $endpoint, $results);

        $response->setContentType($prepared_response['http_content_type']);
        $response->setBody($prepared_response['body']);
        $response->send();
      }
    }
    else {
      throw new Exception('Invalid API version');
    }
  }

  /**
   * Prepares API results as either JSON or XML.
   *
   * @param int $code
   *   An HTTP response status code.
   * @param string $message
   *   The API response message (e.g. 'Success' or an error message).
   * @param OnesiteApiBase $endpoint
   *   An API endpoint.
   * @param OnesiteApiResults|null $results
   *   Results from the query or NULL if no results.
   * @param bool $deprecated
   *   Whether or the version of the API being called is deprecated (TRUE)
   *   or supported (FALSE).
   *
   * @return array
   *   An associative array containing:
   *   - http_content_type: (string) Content-Type HTTP header value.
   *   - body: (string) The response body.
   */
  public static function prepareReturn($code, $message, OnesiteApiBase $endpoint, OnesiteApiResults $results = NULL, $deprecated = FALSE) {
    $return_render['apiResults'] = array(
      'code' => $code,
      'message' => $message,
      'apiStatus' => $deprecated ? 'deprecated' : 'supported',
    );

    if ($results && !empty($entities = $results->getEntities())) {
      $total_records = $results->getTotalResultsCount();
      $records_this_page = count($entities);

      $return_render['resultsSummary'] = [
        'totalRecords' => $total_records,
        'recordsPerPage' => $results->getPageCount(),
        'pages' => floor($total_records / $results->getPageCount()),
        'currentPage' => $results->getCurrentPage(),
        'recordsThisPage' => $records_this_page,
      ];
      $return_render['data'] = $entities;
    }

    if ($endpoint->getFormat() == 'json') {
      return [
        'http_content_type' => 'application/json; utf-8',
        'body' => json_encode($return_render, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK),
      ];
    }
    elseif ($endpoint->getFormat() == 'xml') {
      // Process data for XML formatting.
      module_load_include('php', 'onesite_api', 'lib/array-to-xml/src/ArrayToXml');
      $return_render = $endpoint->prepareDataForXml($return_render);
      $arrayToXml = new ArrayToXml($return_render);
      $arrayToXml->setDomProperties(['formatOutput' => TRUE]);
      return [
        'http_content_type' => 'text/xml; utf-8',
        'body' => $arrayToXml->prettify()->toXml(),
      ];
    }
    // If format is invalid, then render return as plaintext.
    else {
      return [
        'http_content_type' => 'text/plain; utf-8',
        'body' => $message,
      ];
    }
  }

}
