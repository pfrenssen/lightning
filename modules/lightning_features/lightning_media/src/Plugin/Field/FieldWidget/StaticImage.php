<?php

namespace Drupal\lightning_media\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;

/**
 * Plugin implementation of the 'static_image' widget.
 *
 * This widget is identical to the image_image widget it extends, except that it
 * suppresses the Remove button and the link to the uploaded file(s).
 *
 * @FieldWidget(
 *   id = "static_image",
 *   label = @Translation("Static image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class StaticImage extends ImageWidget {

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    // Don't allow the remove button to be displayed.
    $element['remove_button']['#access'] = FALSE;

    // Suppress the file link.
    foreach ($element['fids']['#value'] as $fid) {
      $element['file_' . $fid]['#access'] = FALSE;
    }

    return $element;
  }

}
