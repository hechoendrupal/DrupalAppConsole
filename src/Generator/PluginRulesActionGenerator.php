<?php
/**
 * @file
 * Contains \Drupal\AppConsole\Generator\PluginBlockGenerator.
 */

namespace Drupal\AppConsole\Generator;

class PluginRulesActionGenerator extends Generator
{
    /**
     * Generator Plugin RulesAction
     * @param  $module
     * @param  $class_name
     * @param  $label
     * @param  $plugin_id
     * @param  $category
     * @param  $context
     */
    public function generate($module, $class_name, $label, $plugin_id, $category, $context)
    {
        $parameters = [
          'module' => $module,
          'class_name' => $class_name,
          'label' => $label,
          'plugin_id' => $plugin_id,
          'category' => $category,
          'context' => $context,
        ];

        $this->renderFile(
          'module/src/Plugin/RulesAction/rulesaction.php.twig',
          $this->getPluginPath($module, 'RulesAction') . '/' . $class_name . '.php',
          $parameters
        );
    }
}
