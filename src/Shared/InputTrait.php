<?php

/**
 * @file
 * Contains Drupal\Console\Shared\InputTrait.
 */

namespace Drupal\Console\Shared;

/**
 * Class CreateTrait
 * @package Drupal\Console\Command
 */
trait InputTrait
{
    /**
     * @return array
     */
    private function inlineValueAsArray($inputValue)
    {
        $inputArrayValue = [];
        foreach ($inputValue as $key => $value) {
            if (!is_array($value)) {
                $inputValueItems = [];
                foreach (explode(" ", $value) as $inputKeyValueItem) {
                    list($inputKeyItem, $inputValueItem) = explode(":", $inputKeyValueItem);
                    $inputValueItems[$inputKeyItem] = $inputValueItem;
                }
                $inputArrayValue[$key] = $inputValueItems;
            }
        }

        return $inputArrayValue?$inputArrayValue:$inputValue;
    }
}
