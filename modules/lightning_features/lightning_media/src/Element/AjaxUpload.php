<?php

namespace Drupal\lightning_media\Element;

use Drupal\Component\Utility\Html;

/**
 * An interactive, AJAX-ey file upload form element.
 *
 * @FormElement("ajax_upload")
 */
class AjaxUpload extends InteractiveUpload {

  /**
   * {@inheritdoc}
   */
  public static function process(array $element) {
    $id = implode('-', $element['#parents']);
    $element['#ajax']['wrapper'] = Html::cleanCssIdentifier($id);
    $element['#prefix'] = '<div id="' . $element['#ajax']['wrapper'] . '">';
    $element['#suffix'] = '</div>';

    $element['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -100,
    ];

    return parent::process($element);
  }

  /**
   * {@inheritdoc}
   */
  protected static function processFile(array $element) {
    $element = parent::processFile($element);

    $element['remove']['#ajax'] = [
      'callback' => static::class . '::getSelf',
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
      'callback' => static::class . '::getSelf',
      'wrapper' => $element['#ajax']['wrapper'],
    ];
    return $element;
  }

}
