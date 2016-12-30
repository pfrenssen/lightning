<?php

namespace Acquia\Lightning;

use Composer\Script\Event;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\NestedArray;

/**
 * Contains callback functions for Lightning's Composer scripts.
 */
final class ScriptHelper {

  /**
   * Sets a value in a YAML file.
   *
   * Arguments:
   *  - The key to set. Nested keys are separated by periods, like foo.baz.
   *  - The value to set.
   *  - The path to the YAML file.
   *
   * @param \Composer\Script\Event $event
   *   The event object.
   */
  public static function setValue(Event $event) {
    $arguments = $event->getArguments();

    $info = file_get_contents($arguments[2]);
    $info = Yaml::decode($info);
    NestedArray::setValue($info, explode('.', $arguments[0]), $arguments[1]);
    file_put_contents($arguments[2], Yaml::encode($info));
  }

}
