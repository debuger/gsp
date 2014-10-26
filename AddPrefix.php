<?php
/**
 * Copyright (C) 2013 rym <rym.the.great@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @license http://www.gnu.org/licenses/ GPLv3
 */
namespace Stylecow\Plugins;
use Stylecow\Css;

/**
 * Class AddPrefix
 *
 * Plugin for Stylecow\Css to add prefix to each selector.
 * @package Stylecow\Plugins
 */
class AddPrefix {
    const POSITION = 10;

    /**
     * Add prefix to selectors.
     * @param Css $css The css object.
     * @param string $prefix Prefix to add.
     */
    static public function apply (Css $css, $prefix = '') {
        if (empty($prefix)) {
            return;
        }
        $css->executeRecursive(function ($code) use ($prefix) {
            if (!$code->selector->type) {
                $selectors = $code->selector->get();
                if (!empty($selectors)) {
                    foreach ($selectors as &$v) {
                        $v = $prefix . ' ' . $v;
                    }
                    unset($v);
                }
                $code->selector->set($selectors);
            }
        });
    }
}
