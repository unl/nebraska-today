<?php

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
   * Sends the HTTP response.
   */
  public function send() {
    drupal_add_http_header('status', $this->code);
    drupal_add_http_header('Content-Type', $this->contentType);
    print $this->body;
  }

}
