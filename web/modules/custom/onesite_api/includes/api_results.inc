<?php

/**
 * Defines an API results object.
 */
class OnesiteApiResults {

  /**
   * An array of processed entities.
   *
   * @var array
   */
  protected $entities;

  /**
   * The current page of results.
   *
   * @var int
   */
  protected $currentPage;

  /**
   * The total number of results pages.
   *
   * @var int
   */
  protected $pageCount;

  /**
   * The total number of results available.
   *
   * @var int
   */
  protected $totalResultsCount;

  /**
   * Creates a OnesiteApiResults object.
   */
  public function __construct($entities, $current_page, $page_count, $total_results_count) {
    $this->entities = $entities;
    $this->currentPage = $current_page;
    $this->pageCount = $page_count;
    $this->totalResultsCount = $total_results_count;
  }

  /**
   * Gets the the processed entities.
   *
   * @return array
   *   An array of processed entities.
   */
  public function getEntities() {
    return $this->entities;
  }

  /**
   * Gets the current page of results.
   *
   * @return int
   *   The current page of results.
   */
  public function getCurrentPage() {
    return $this->currentPage;
  }

  /**
   * Gets the total number of results pages.
   *
   * @return int
   *   The total number of results pages.
   */
  public function getPageCount() {
    return $this->pageCount;
  }

  /**
   * Gets the total number of results available.
   *
   * @return int
   *   The total number of results available.
   */
  public function getTotalResultsCount() {
    return $this->totalResultsCount;
  }

}
