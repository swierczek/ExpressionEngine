<?php
/**
 * This source file is part of the open source project
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2022, Packet Tide, LLC (https://www.packettide.com)
 * @license   https://expressionengine.com/license Licensed under Apache License, Version 2.0
 */

namespace ExpressionEngine\Service\Validation\Rule;

use ExpressionEngine\Library\Filesystem\Filesystem;
use ExpressionEngine\Service\Validation\ValidationRule;

/**
 * Writable Validation Rule
 */
class Writable extends ValidationRule
{
    protected $all_values = array();

    public function validate($key, $value)
    {
        $filesystem = ee('Filesystem');

        if (array_key_exists('filesystem', $this->all_values)) {
            $filesystem = $this->all_values['filesystem'];
            unset($this->all_values['filesystem']);
        }

        return $filesystem->isWritable(parse_config_variables($value, $this->all_values));
    }

    public function getLanguageKey()
    {
        return 'invalid_path';
    }

    public function setAllValues(array $values)
    {
        $this->all_values = $values;
    }
}

// EOF
