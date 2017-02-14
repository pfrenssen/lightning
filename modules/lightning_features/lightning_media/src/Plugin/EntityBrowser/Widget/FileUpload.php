<?php

namespace Drupal\lightning_media\Plugin\EntityBrowser\Widget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\lightning_media\Element\AjaxUpload;
use Drupal\media_entity\MediaInterface;

/**
 * An Entity Browser widget for creating media entities from uploaded files.
 *
 * @EntityBrowserWidget(
 *   id = "file_upload",
 *   label = @Translation("File Upload"),
 *   description = @Translation("Allows creation of media entities from file uploads."),
 *   bundle_resolver = "file_upload"
 * )
 */
class FileUpload extends EntityFormProxy {

  /**
   * {@inheritdoc}
   */
  protected function getInputValue(FormStateInterface $form_state) {
    return $form_state->getValue(['input', 'fid']);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $entities = parent::prepareEntities($form, $form_state);

    $get_file = function (MediaInterface $entity) {
      $type_config = $entity->getType()->getConfiguration();
      return $entity->get($type_config['source_field'])->entity;
    };

    if ($this->configuration['return_file']) {
      return array_map($get_file, $entities);
    }
    else {
      return $entities;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    $form['input'] = [
      '#type' => 'ajax_upload',
      '#title' => $this->t('File'),
      '#process' => [
        [AjaxUpload::class, 'process'],
        [$this, 'processUploadElement'],
      ],
      '#upload_validators' => [
        'lightning_media_validate_upload' => [
          $this->getPluginId(),
          $this->getConfiguration(),
        ],
      ],
    ];

    return $form;
  }

  /**
   * Validates an uploaded file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The uploaded file.
   *
   * @return string[]
   *   An array of errors. If empty, the file passed validation.
   */
  public function validateFile(FileInterface $file) {
    $entity = $this->generateEntity($file);

    if ($entity) {
      $type_config = $entity->getType()->getConfiguration();
      /** @var \Drupal\file\Plugin\Field\FieldType\FileItem $item */
      $item = $entity->get($type_config['source_field'])->first();
      return file_validate($file, $item->getUploadValidators());
    }
    else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\media_entity\MediaInterface $entity */
    $entity = $element['entity']['#entity'];

    $type_config = $entity->getType()->getConfiguration();
    /** @var \Drupal\file\Plugin\Field\FieldType\FileItem $item */
    $item = $entity->get($type_config['source_field'])->first();
    /** @var FileInterface $file */
    $file = $item->entity;

    // Prepare the file's permanent home.
    $dir = $item->getUploadLocation();
    file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    $destination = $dir . '/' . $file->getFilename();
    if ($file->getFileUri() != $destination) {
      $file = file_move($file, $destination);
      $entity->set($type_config['source_field'], $file)->save();
    }
    $file->setPermanent();
    $file->save();

    $selection = [
      $this->configuration['return_file'] ? $file : $entity,
    ];
    $this->selectEntities($selection, $form_state);
  }

  /**
   * Processes the upload element.
   *
   * @param array $element
   *   The upload element.
   *
   * @return array
   *   The processed upload element.
   */
  public function processUploadElement(array $element) {
    $element['upload']['#ajax']['callback'] =
    $element['remove']['#ajax']['callback'] = [$this, 'onAjax'];

    $element['remove']['#value'] = $this->t('Cancel');

    return $element;
  }

  /**
   * AJAX callback. Responds when a file has been uploaded.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function onAjax(array &$form, FormStateInterface $form_state) {
    $element = AjaxUpload::getSelf($form, $form_state);

    $response = new AjaxResponse();

    $command = new ReplaceCommand('#' . $element['#ajax']['wrapper'], $element);
    $response->addCommand($command);

    $command = new ReplaceCommand('#ief-target', $form['widget']['entity']);
    $response->addCommand($command);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration['return_file'] = FALSE;
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['return_file'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Return source file entity'),
      '#default_value' => $this->configuration['return_file'],
      '#description' => $this->t('If checked, the source file(s) of the media entity will be returned from this widget.'),
    ];
    return $form;
  }

}
