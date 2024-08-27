<?php

namespace Drupal\unl_news_lmmigrate\Form;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Deletes all remaining parallax_image blocks.
 *
 * @ingroup unl_news_lmmigrate
 */
class DeleteOldBlocks extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'unl_news_lmmigrate_cleanup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<p>Lead media migrator: clean up old blocks.</p>';

    $form['actions'] = array(
      '#type' => 'actions',
      'submit' => array(
        '#type' => 'submit',
        '#value' => 'Proceed',
      ),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $ids = \Drupal::entityQuery('block_content')
      ->accessCheck(FALSE)
      ->condition('type', 'parallax_image')
      ->execute();

    $batch = [
      'title' => t('Deleting Lead Media custom blocks'),
      'operations' => [],
      'init_message' => t('Process is starting.'),
      'progress_message' => t('Processed @current out of @total. Estimated time: @estimate.'),
      'error_message' => t('The process has encountered an error.'),
    ];

    foreach ($ids as $id) {
      $batch['operations'][] = [
        ['\Drupal\unl_news_lmmigrate\Form\DeleteOldBlocks', 'deleteBlock'], [$id]
      ];
    }

    batch_set($batch);
    \Drupal::messenger()->addMessage('Deleted ' . count($ids) . ' Lead Media block instances');

    $form_state->setRebuild(FALSE);
  }

  /**
   * Deletes a block.
   */
  public static function deleteBlock($id, $storageHandler) {
    $block = BlockContent::load($id);
    $block->delete();

    $context['results'][] = $id;
    $context['message'] = t('Deleted @id', array('@id' => $id));
  }

}
