<?php

namespace Drupal\lightning_media\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;

/**
 * An interactive, AJAX-ey file upload form element.
 *
 * @FormElement("ajax_upload")
 */
class AjaxUpload extends InteractiveUpload {

  /**
   * {@inheritdoc}
   */
  public static function process(array $element, FormStateInterface $form_state) {
    $element = parent::process($element, $form_state);

    $id = implode('-', $element['#parents']);
    $element['#ajax']['wrapper'] = Html::cleanCssIdentifier($id);
    $element['#prefix'] = '<div id="' . $element['#ajax']['wrapper'] . '">';
    $element['#suffix'] = '</div>';

    $element['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -100,
    ];

    $element['upload']['#ajax'] = $element['remove']['#ajax'] = [
      'callback' => [static::class, 'getSelf'],
      'wrapper' => $element['#ajax']['wrapper'],
    ];

    return $element;
  }

}
