<?php
/**
 * Copyright (C) 2022  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;

/**
 * Table tl_isotope_packaging_slip_product_collection
 */
$GLOBALS['TL_DCA']['tl_isotope_packaging_slip_product_collection'] = [

  // Config
  'config' => [
    'ptable' => IsotopePackagingSlipModel::getTable(),
    'sql' => [
      'keys' => [
        'id' => 'primary',
        'pid' => 'index',
        'product_id,pid' => 'index',
      ],
    ],
  ],

  'fields' => [
    'id' => [
      'sql' => "int(10) unsigned NOT NULL auto_increment",
    ],
    'pid' => [
      'foreignKey' => IsotopePackagingSlipModel::getTable() . '.document_number',
      'sql' => "int(10) unsigned NOT NULL default '0'",
      'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
    ],
    'tstamp' => [
      'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'product_id' => [
      'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'quantity' => [
      'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'value' => array
    (
      'inputType'               => 'text',
      'eval'                    => array('mandatory'=>true, 'rgxp'=>'digit', 'tl_class' => 'clr'),
      'sql'                     => "decimal(12,2) NOT NULL default '0.00'",
    ),
    'document_number' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('disabled'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'is_available' => array
    (
      'filter'                  => true,
      'inputType'               => 'radio',
      'eval'                    => array('tl_class' => 'w50'),
      'options'                 => array('0', '1', '-1'),
      'sql'                     => "int(10) signed NOT NULL default 0",
      'default'                 => '0',
    ),
  ],
];
