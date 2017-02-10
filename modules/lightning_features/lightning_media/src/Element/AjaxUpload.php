<?php

namespace Drupal\lightning_media\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;

/**
 * @FormElement("ajax_upload")
 */
class AjaxUpload extends InteractiveUpload {

  /**
   * {@inheritdoc}
   */
  public static function process(array $element) {
    $id = implode('_', $element['#parents']);
    $element['#ajax']['wrapper'] = $id;
    $element['#prefix'] = '<div id="' . Html::cleanCssIdentifier($id) . '">';
    $element['#suffix'] = '</div>';

    return parent::process($element);
  }

  /**
   * {@inheritdoc}
   */
  protected static function processFile(array $element) {
    $element = parent::processEmpty($element);

    $element['remove']['#ajax'] = [
      'callback' => static::class . '::onRemove',
      'wrapper' => $element['#ajax']['wrapper'],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected static function processEmpty(array $element) {
    $element = parent::processEmpty($element);

    $element['upload']['#ajax'] = [
      'callback' => static::class . '::onUpload',
      'wrapper' => $element['#ajax']['wrapper'],
    ];
    return $element;
  }

  public static function onUpload(array &$form, FormStateInterface $form_state) {
    return static::getSelf($form, $form_state);
  }

  public static function onRemove(array &$form, FormStateInterface $form_state) {
    return static::getSelf($form, $form_state);
  }

}
