<?php

namespace Drupal\unl_news_lmmigrate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Provides a form to kick off the move from parallax_image custom block
 * references to plain fields on the article content type
 *
 * @ingroup unl_news_lmmigrate
 */
class KickOffForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'unl_news_lmmigrate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#prefix'] = '<p>Lead media migrator.</p>';

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
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type','article')
      ->execute();

    //$nids = ['13308', '13284', '13224'];
    //$nids = ['173888'];

    $batch = [
      'title' => t('Migrating Lead Media fields'),
      'operations' => [],
      'init_message' => t('Migration process is starting.'),
      'progress_message' => t('Processed @current out of @total. Estimated time: @estimate.'),
      'error_message' => t('The process has encountered an error.'),
    ];

    foreach ($nids as $nid) {
      $batch['operations'][] = [
        ['\Drupal\unl_news_lmmigrate\Form\KickOffForm', 'migrateLeadMedia'], [$nid]
      ];
    }

    batch_set($batch);
    \Drupal::messenger()->addMessage('Migrated ' . count($nids) . ' Lead Media instances');

    $form_state->setRebuild(FALSE);
  }

  /**
   * Migrates a custom block reference into plain fields.
   */
  public static function migrateLeadMedia($nid) {
    $node = Node::load($nid);
    $changed = $node->getChangedTime();

    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $referenceItem */
    $referenceItem = $node->get('field_article_lead_image')->first();

    if ($referenceItem) {
      /** @var \Drupal\Core\Entity\Plugin\DataType\EntityReference $entityReference */
      $entityReference = $referenceItem->get('entity');

      /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $entityAdapter */
      $entityAdapter = $entityReference->getTarget();

      /** @var \Drupal\Core\Entity\EntityInterface $referencedEntity */
      $referencedEntity = $entityAdapter->getValue();

      $media = $referencedEntity->get('field_image');
      $cutline = $referencedEntity->get('field_cutline');
      $title_placement = $referencedEntity->get('field_title_placement')->value;

      $title_placement = strtolower(str_replace(' ', '_', str_replace('Featured : ', '', $title_placement)));

      $node->field_article_lead_media = $media;
      $node->field_article_lead_cutline = $cutline;
      $node->field_article_title_placement = [['value' => $title_placement]];
      $node->setChangedTime($changed);
      $node->save();

      $node->set('field_article_lead_image', []);
      $node->setChangedTime($changed);
      $node->save();

      $referencedEntity->delete();
    }
    else {
      $node->field_article_title_placement = [['value' => 'basic']];
      $node->setChangedTime($changed);
      $node->save();

      // Have to save twice to get the Changed timestamp to stick.
      $node->setChangedTime($changed);
      $node->save();
    }

    $context['results'][] = $nid;
    $context['message'] = t('Fixed @nid', array('@nid' => $nid));
  }

}
