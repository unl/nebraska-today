<?php

namespace Drupal\onesite_api;

use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a response object.
 */
class OnesiteApiReponse {

  /**
   * An HTTP response status code.
   *
   * @var int
   */
  protected $code = 200;

  /**
   * A value for the Content-Type HTTP header.
   *
   * @var string
   */
  protected $contentType = 'text/plain; utf-8';

  /**
   * The response body.
   *
   * @var string
   */
  protected $body = 'No content provided.';

  /**
   * Gets the response code.
   */
  public function getResponseCode() {
    return $this->code;
  }

  /**
   * Sets the response code.
   *
   * @var int $code
   *   An HTTP response status code.
   */
  public function setResponseCode($code) {
    $this->code = $code;
  }

  /**
   * Gets the HTTP Content-Type header.
   */
  public function getContentType() {
    return $this->contentType;
  }

  /**
   * Sets the HTTP Content-Type header.
   *
   * @var string $content_type
   *   A value for the Content-Type HTTP header.
   */
  public function setContentType($content_type) {
    $this->contentType = $content_type;
  }

  /**
   * Gets the response body.
   */
  public function getBody() {
    return $this->body;
  }

  /**
   * Sets the response body.
   *
   * @var string $body
   *   The response body.
   */
  public function setBody($body) {
    $this->body = $body;
  }

  /**
   * Prepares the HTTP response.
   */
  public function prepareResponse(Response $response) {
    $response->headers->set('status', $this->code);
    $response->headers->set('Content-Type', $this->contentType);
    $response->setContent($this->body);

    return $response;
  }

}
