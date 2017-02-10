<?php

namespace Drupal\lightning_media\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\file\Entity\File;

/**
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

  public static function process(array $element) {
    return $element['#value']
      ? static::processFile($element)
      : static::processEmpty($element);
  }

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
        [$element['#parents']],
      ],
      '#submit' => [
        [static::class, 'remove'],
      ],
    ];
    return $element;
  }

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
        [$element['#parents']],
      ],
      '#submit' => [
        [static::class, 'upload'],
      ],
    ];
    return $element;
  }

  protected static function getSelf(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    return NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -1));
  }

  public static function upload(array &$form, FormStateInterface $form_state) {
    $self = static::getSelf($form, $form_state);

    $form_state->setValueForElement($self, $self['file']['#value']);
    NestedArray::setValue($form_state->getUserInput(), $self['#parents'], $self['file']['#value']);

    $form_state->setRebuild();
  }

  public static function remove(array &$form, FormStateInterface $form_state) {
    $self = static::getSelf($form, $form_state);

    $file = File::load($self['fid']['#value']);
    $file->delete();

    $form_state->setValueForElement($self, NULL);
    NestedArray::setValue($form_state->getUserInput(), $self['#parents'], NULL);

    $uri = $file->getFileUri();
    if (file_exists($uri)) {
      \Drupal::service('file_system')->unlink($uri);
    }

    $form_state->setRebuild();
  }

}
