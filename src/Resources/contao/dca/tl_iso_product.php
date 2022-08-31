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

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_iso_product']['fields']['isotope_packaging_slip_scheduled_shipping_date'] = [
  'filter'                  => true,
  'inputType'               => 'text',
  'flag'                    => 8,
  'default'                 => time(),
  'eval'                    => array('mandatory'=>false, 'rgxp'=>'date', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
  'sql'                     => "varchar(10) NOT NULL default ''",
  'attributes'            => array( 'legend'=>'isostock_legend' ),
];
$GLOBALS['TL_DCA']['tl_iso_product']['fields']['isotope_packaging_slip_scheduled_picking_date'] = [
  'filter'                  => true,
  'inputType'               => 'text',
  'flag'                    => 8,
  'default'                 => time(),
  'eval'                    => array('mandatory'=>false, 'rgxp'=>'date', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
  'sql'                     => "varchar(10) NOT NULL default ''",
  'attributes'            => array( 'legend'=>'isostock_legend' ),
];