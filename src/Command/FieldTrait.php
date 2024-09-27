<?php

/**
 * @file
 * Contains Drupal\Console\Command\FormTrait.
 */

namespace Drupal\sammy\Command;

use Drupal\Console\Style\DrupalStyle;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * Class Field
 * @package Drupal\Console\Command
 */
trait FieldTrait
{
  /**
   * @param DrupalStyle $io
   *
   * @return mixed
   */
  public function formQuestion(DrupalStyle $io)
  {
    if ($io->confirm(
      $this->trans('commands.common.questions.inputs.confirm'),
      true
    )) {
      $input_types = [
        'fieldset',
      ];

      /** @var FieldTypePluginManagerInterface $fieldInfoManager */
      $fieldInfoManager = $this->getService('plugin.manager.field.field_type');
      if (!$fieldInfoManager) {
        return false;
      }

      // Gather valid field types.
      $field_type_options = array();
      foreach ($fieldInfoManager->getSortedDefinitions($fieldInfoManager->getUIDefinitions()) as $category => $field_types) {

        foreach ($field_types as $name => $field_type) {

          $field_type_options[$category][$name] = $field_type->render();
        }
      }

      $inputs = [];
      $fieldSets = [];
      while (true) {
        $input_type = $io->choiceNoList(
          $this->trans('commands.common.questions.inputs.type'),
          $field_type_options,
          null,
          true
        );

       /* if (empty($input_type)) {
          break;
        }*/

        // Label for input
        $inputLabelMessage = $input_type == 'fieldset'?$this->trans('commands.common.questions.inputs.title'):$this->trans('commands.common.questions.inputs.label');
        $input_label = $io->ask(
          $inputLabelMessage,
          null
        );

        // Machine name
        $input_machine_name = $this->getStringHelper()->createMachineName($input_label);

        $input_name = $io->ask(
          $this->trans('commands.common.questions.inputs.machine_name'),
          $input_machine_name
        );

        if ($input_type == 'fieldset') {
          $fieldSets[$input_machine_name] = $input_label;
        }

        $inputFieldSet = '';
        if ($input_type != 'fieldset' && !empty($fieldSets)) {
          $inputFieldSet = $io->choiceNoList(
            $this->trans('commands.common.questions.inputs.fieldset'),
            $fieldSets,
            null,
            true
          );

          $inputFieldSet = array_search($inputFieldSet, $fieldSets);
        }

        $maxlength = null;
        $size = null;
        if (in_array($input_type, array('textfield', 'password', 'password_confirm'))) {
          $maxlength = $io->ask(
            'Maximum amount of characters',
            '64'
          );

          $size = $io->ask(
            'Width of the textfield (in characters)',
            '64'
          );
        }

        if ($input_type == 'select') {
          $size = $io->askEmpty(
            'Size of multiselect box (in lines)',
            '5'
          );
        }

        $input_options = '';
        if (in_array($input_type, array('checkboxes', 'radios', 'select'))) {
          $input_options = $io->ask(
            'Input options separated by comma'
          );
        }

        // Prepare options as an array
        if (strlen(trim($input_options))) {
          // remove spaces in options and empty options
          $input_options = array_filter(array_map('trim', explode(',', $input_options)));
          // Create array format for options
          foreach ($input_options as $key => $value) {
            $input_options_output[$key] = "'$value' => \$this->t('".$value."')";
          }

          $input_options = 'array('.implode(', ', $input_options_output).')';
        }

        // Description for input
        $input_description = $io->askEmpty(
          $this->trans('commands.common.questions.inputs.description')
        );

        if ($input_type != 'fieldset') {
          // Default value for input
          $default_value = $io->askEmpty(
            $this->trans('commands.common.questions.inputs.default-value')
          );
        }

        // Weight for input
        $weight = $io->ask(
          $this->trans('commands.common.questions.inputs.weight'),
          '0'
        );

        array_push(
          $inputs,
          [
            'name' => $input_name,
            'type' => $input_type,
            'label' => $input_label,
            'options' => $input_options,
            'description' => $input_description,
            'maxlength' => $maxlength,
            'size' => $size,
            'default_value' => $default_value,
            'weight' => $weight,
            'fieldset' => $inputFieldSet,
          ]
        );
      }

      return $inputs;
    }

    return null;
  }
}
