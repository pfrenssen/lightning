<?php

namespace Drupal\lightning_media\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\File as FileElement;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @FormElement("upload")
 */
class Upload extends FileElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();

    $info['#upload_location'] = 'public://';
    $info['#upload_validators'] = [];
    $info['#element_validate'] = [
      [static::class, 'validate'],
    ];

    return $info;
  }

  public static function validate(array $element, FormStateInterface $form_state) {
    if ($element['#value']) {
      $file = File::load($element['#value']);

      $errors = file_validate($file, $element['#upload_validators']);
      foreach ($errors as $error) {
        $form_state->setError($element, (string) $error);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function processFile(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#name'] = implode('_', $element['#parents']);
    $form_state->setHasFileElement();
    return parent::processFile($element, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $id = implode('_', $element['#parents']);

    $upload = \Drupal::request()->files->get($id);

    if ($upload instanceof UploadedFile) {
      $destination = \Drupal::service('file_system')
        ->realPath($element['#upload_location']);

      $name = file_munge_filename($upload->getClientOriginalName(), NULL);
      $name = file_create_filename($name, $destination);
      $name = $upload->move($destination, $name)->getFilename();

      $uri = $element['#upload_location'];
      if (substr($uri, -1) != '/') {
        $uri .= '/';
      }
      $uri .= $name;

      $file = File::create([
        'uri' => $uri,
        'uid' => \Drupal::currentUser()->id(),
      ]);
      $file->setTemporary();
      $file->save();
      \Drupal::request()->files->remove($id);

      return $file->id();
    }
    else {
      return NULL;
    }
  }

}
