<?php

namespace Drupal\lightning_media\Plugin\EntityBrowser\Widget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\file\FileInterface;
use Drupal\lightning_media\BundleResolverInterface;
use Drupal\lightning_media\Element\AjaxUpload;
use Drupal\lightning_media\SourceFieldTrait;
use Drupal\media_entity\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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

  use SourceFieldTrait;

  /**
   * FileUpload constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param WidgetValidationManager $widget_validation_manager
   *   The widget validation manager.
   * @param BundleResolverInterface $bundle_resolver
   *   The media bundle resolver.
   * @param AccountInterface $current_user
   *   The currently logged in user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $event_dispatcher, EntityManagerInterface $entity_manager, WidgetValidationManager $widget_validation_manager, BundleResolverInterface $bundle_resolver, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $event_dispatcher, $entity_manager, $widget_validation_manager, $bundle_resolver, $current_user);
    $this->fieldStorage = $entity_manager->getStorage('field_config');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $bundle_resolver = $plugin_definition['bundle_resolver'];

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('entity.manager'),
      $container->get('plugin.manager.entity_browser.widget_validation'),
      $container->get('plugin.manager.lightning_media.bundle_resolver')->createInstance($bundle_resolver),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getInputValue(FormStateInterface $form_state) {
    $value = parent::getInputValue($form_state);
    if ($value) {
      return $this->entityTypeManager->getStorage('file')->load($value);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $entities = parent::prepareEntities($form, $form_state);

    if ($this->configuration['return_file']) {
      return array_map([$this, 'getFile'], $entities);
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
        [$this, 'processInitialFileElement'],
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
      $field = $this->getSourceField($entity)->getName();
      /** @var \Drupal\file\Plugin\Field\FieldType\FileItem $item */
      $item = $entity->get($field)->first();
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

    $file = $this->getFile($entity);
    $file->setPermanent();
    $file->save();

    $selection = [
      $this->configuration['return_file'] ? $file : $entity,
    ];
    $this->selectEntities($selection, $form_state);
  }

  /**
   * Returns the source file of a media entity.
   *
   * @param \Drupal\media_entity\MediaInterface $entity
   *   The media entity.
   *
   * @return \Drupal\file\FileInterface
   *   The source file.
   */
  protected function getFile(MediaInterface $entity) {
    $field = $this->getSourceField($entity)->getName();
    return $entity->get($field)->entity;
  }

  /**
   * Processes the file element that is NOT part of the entity form.
   *
   * @param array $element
   *   The file element.
   *
   * @return array
   *   The processed file element.
   */
  public function processInitialFileElement(array $element) {
    if ($element['#value']) {
      $element['remove']['#ajax']['callback'] = [$this, 'onRemove'];
      $element['remove']['#value'] = $this->t('Cancel');
    }
    else {
      $element['upload']['#ajax']['callback'] = [$this, 'onUpload'];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function processEntityForm(array $entity_form) {
    $type_config = $entity_form['#entity']->getType()->getConfiguration();
    $field = $type_config['source_field'];

    if (isset($entity_form[$field])) {
      $entity_form[$field]['widget'][0]['#process'][] = [$this, 'processEntityFormFileElement'];
    }

    return parent::processEntityForm($entity_form);
  }

  /**
   * Processes the file element that IS part of the entity form.
   *
   * @param array $element
   *   The file element.
   *
   * @return array
   *   The processed file element.
   */
  public function processEntityFormFileElement(array $element) {
    $element['remove_button']['#access'] = FALSE;

    if ($element['#default_value']) {
      $key = 'file_' . $element['#default_value']['target_id'];
      $element[$key]['#access'] = FALSE;
    }

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
  public function onUpload(array &$form, FormStateInterface $form_state) {
    $element = AjaxUpload::getSelf($form, $form_state);

    $response = new AjaxResponse();

    $selector = '#' . $element['#ajax']['wrapper'];
    $command = new ReplaceCommand($selector, $element);
    $response->addCommand($command);

    $complete_form = $form_state->getCompleteForm();
    $selector = '#' . $complete_form['widget']['ief_target']['#id'];
    $content = $this->getEntityForm($complete_form, $form_state);

    $command = new HtmlCommand($selector, $content);
    $response->addCommand($command);
    return $response;
  }

  /**
   * AJAX callback. Responds when the uploaded file is removed.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function onRemove(array &$form, FormStateInterface $form_state, Request $request) {
    $element = AjaxUpload::getSelf($form, $form_state);

    $response = new AjaxResponse();

    $selector = '#' . $element['#ajax']['wrapper'];
    $command = new ReplaceCommand($selector, $element);
    $response->addCommand($command);

    $complete_form = $form_state->getCompleteForm();
    $selector = '#' . $complete_form['widget']['ief_target']['#id'];

    $command = new InvokeCommand($selector, 'empty');
    return $response->addCommand($command);
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
