<?php

namespace Drupal\lightning_media\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\file\Entity\File;

/**
 * A form element for uploading or deleting files interactively.
 *
 * @FormElement("interactive_upload")
 */
class InteractiveUpload extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#tree' => TRUE,
      '#input' => TRUE,
      '#title' => NULL,
      '#default_value' => NULL,
      '#process' => [
        [static::class, 'process'],
      ],
      '#upload_location' => 'public://',
      '#upload_validators' => [],
    ];
  }

  /**
   * Processes the element.
   *
   * @param array $element
   *   The unprocessed element.
   *
   * @return array
   *   The processed element.
   */
  public static function process(array $element) {
    return $element['#value']
      ? static::processFile($element)
      : static::processEmpty($element);
  }

  /**
   * Processes the element when there is a default value.
   *
   * @param array $element
   *   The unprocessed element.
   *
   * @return array
   *   The processed element.
   */
  protected static function processFile(array $element) {
    $element['file'] = [
      '#theme' => 'file_link',
      '#file' => File::load($element['#value']),
    ];
    $element['fid'] = [
      '#type' => 'hidden',
      '#default_value' => $element['#value'],
    ];
    $element['remove'] = [
      '#type' => 'submit',
      '#value' => t('Remove'),
      '#limit_validation_errors' => [
        $element['#parents'],
      ],
      '#submit' => [
        [static::class, 'remove'],
      ],
    ];
    return $element;
  }

  /**
   * Processes the element when there is no default value.
   *
   * @param array $element
   *   The unprocessed element.
   *
   * @return array
   *   The processed element.
   */
  protected static function processEmpty(array $element) {
    $element['file'] = [
      '#type' => 'upload',
      '#title' => $element['#title'],
      '#upload_location' => $element['#upload_location'],
      '#upload_validators' => $element['#upload_validators'],
    ];
    $element['upload'] = [
      '#type' => 'submit',
      '#value' => t('Upload'),
      '#limit_validation_errors' => [
        $element['#parents'],
      ],
      '#submit' => [
        [static::class, 'upload'],
      ],
    ];
    return $element;
  }

  /**
   * Returns the root element for a triggering element.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The root element that contains the triggering element.
   */
  public static function getSelf(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    return NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -1));
  }

  /**
   * Submit function when the Upload button is clicked.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function upload(array &$form, FormStateInterface $form_state) {
    $self = static::getSelf($form, $form_state);

    $form_state->setValueForElement($self, $self['file']['#value']);
    NestedArray::setValue($form_state->getUserInput(), $self['#parents'], $self['file']['#value']);

    $form_state->setRebuild();
  }

  /**
   * Submit function when the Remove button is clicked.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function remove(array &$form, FormStateInterface $form_state) {
    $self = static::getSelf($form, $form_state);

    Upload::delete($self['fid']);

    $form_state->setValueForElement($self, NULL);
    NestedArray::setValue($form_state->getUserInput(), $self['#parents'], NULL);

    $form_state->setRebuild();
  }

}
