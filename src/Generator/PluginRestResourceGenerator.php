<?php
/**
 * @file
 * Contains \Drupal\AppConsole\Generator\PluginRestResourceGenerator.
 */

namespace Drupal\AppConsole\Generator;

class PluginRestResourceGenerator extends Generator
{
    /**
     * Generator Plugin Block
     * @param  $module
     * @param  $class_name
     * @param  $plugin_label
     * @param  $plugin_id
     * @param  $plugin_url
     * @param  $states
     */
    public function generate($module, $class_name, $plugin_id, $plugin_label, $plugin_url, $plugin_states)
    {
        $parameters = [
          'module_name' => $module,
          'class_name' => $class_name,
          'plugin_label' => $plugin_label,
          'plugin_id' => $plugin_id,
          'plugin_url' => $plugin_url,
          'plugin_states' => $plugin_states,
        ];

        $this->renderFile(
          'module/src/Plugin/Rest/Resource/rest.php.twig',
          $this->getPluginPath($module, 'rest') . '/resource/' . $class_name . '.php',
          $parameters
        );
    }
}
