<?php
 namespace Drupal\import_json\Form;
 use Drupal\Core\Form\FormBase;
 use Drupal\Core\Form\FormStateInterface;
/**
 * Class BulkDeleteForm.
 *
 * @package Drupal\my_batch\Form
 */
class batchForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'batch_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['batch_submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Process Data'),
    );
    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->sort('created', 'ASC')
      ->execute();
    $batch = array(
      'title' => t('Bulk Delete...'),
      'operations' => array(
        array(
          '\Drupal\my_batch\BulkDeleteNode::bulkDelete',
          array($nids)
        ),
      ),
      'finished' => '\Drupal\my_batch\BulkDeleteNode::bulkDeleteFinishedCallback',
    );
    batch_set($batch);
  }
}