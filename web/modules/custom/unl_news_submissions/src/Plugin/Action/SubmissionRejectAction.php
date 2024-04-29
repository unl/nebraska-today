<?php

namespace Drupal\unl_news_submissions\Plugin\Action;

use Drupal\node\Entity\Node;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
  * Submission process reject article node.
  *
  * @Action(
  *   id = "unl_news_submissions_reject_action",
  *   label = @Translation("Reject submission"),
  *   type = "node",
  *   confirm = FALSE
  * )
  */

class SubmissionRejectAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    if (!$state = $entity->get('field_article_submission_status')->getString()) {
      return $this->t(':title  - can\'t change state',
        [
          ':title' => $entity->getTitle(),
        ]
      );
    }

    switch ($state) {
      case 'pending':
      default:
        $entity->set('field_article_submission_status', 'rejected');
        $entity->save();
        break;
    }

    return $this->t(':title state changed to :state',
      [
        ':title' => $entity->getTitle(),
        ':state' => $entity->get('field_article_submission_status')->getString(),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof Node) {
      $can_update = $object->access('update', $account, TRUE);
      $can_edit = $object->access('edit', $account, TRUE);

      return $can_edit->andIf($can_update);
    }

    return FALSE;
  }
}
