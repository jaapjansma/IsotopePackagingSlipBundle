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

use Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel;

/**
 * Table tl_isotope_packaging_slip_order_collection
 */
$GLOBALS['TL_DCA']['tl_isotope_packaging_slip_order_collection'] = array
(

    // Config
    'config' => array
    (
        'sql' => array
        (
            'keys' => array
            (
                'id' => 'primary',
                'pid' => 'index',
                'order_id,pid' => 'index',
            )
        ),
    ),

    'fields'                        => array
    (
        'id' => array
        (
            'sql'                   => "int(10) unsigned NOT NULL auto_increment",
        ),
        'pid' => array
        (
            'foreignKey'            => PackagingSlipModel::getTable().'.document_number',
            'sql'                   => "int(10) unsigned NOT NULL default '0'",
            'relation'              => array('type'=>'belongsTo', 'load'=>'lazy'),
        ),
        'tstamp' => array
        (
            'sql'                   => "int(10) unsigned NOT NULL default '0'",
        ),
        'order_id' => array
        (
            'sql'                   => "int(10) unsigned NOT NULL default '0'",
        ),
    )
);
