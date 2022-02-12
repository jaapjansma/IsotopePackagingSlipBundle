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

\Contao\System::loadLanguageFile(\Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel::getTable());
\Contao\Controller::loadDataContainer(\Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel::getTable());

$GLOBALS['TL_DCA']['tl_isotope_stock_booking']['fields']['packaging_slip_id'] = array
(
  'inputType'               => 'tableLookup',
  'sql'                     => "int(10) unsigned NOT NULL default 0",
  'eval' => array
  (
    'mandatory'                 => true,
    'doNotSaveEmpty'            => true,
    'tl_class'                  => 'clr',
    'foreignTable'              => 'tl_isotope_packaging_slip',
    'fieldType'                 => 'radio',
    'listFields'                => array(\Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel::getTable().'.document_number'),
    'joins'                     => array(),
    'searchFields'              => array('document_number'),
    'customLabels'              => array
    (
      $GLOBALS['TL_DCA'][\Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel::getTable()]['fields']['document_number']['label'][0],
    ),
    'sqlWhere'                  => '',
    'searchLabel'               => 'Search Packaging Slip',
  ),
);

PaletteManipulator::create()
  ->addField('packaging_slip_id', 'order_id', PaletteManipulator::POSITION_AFTER)
  ->applyToPalette('default', 'tl_isotope_stock_booking');