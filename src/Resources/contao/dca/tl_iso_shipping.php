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

$GLOBALS['TL_DCA']['tl_iso_shipping']['fields']['isotopestock_override_store_account'] = [
  'search' => true,
  'exclude' => true,
  'inputType' => 'checkbox',
  'eval' => ['tl_class' => 'w50 m12', 'submitOnChange' => true],
  'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => '']
];

$GLOBALS['TL_DCA']['tl_iso_shipping']['fields']['isotopestock_store_account'] = [
  'filter'                  => true,
  'inputType'               => 'select',
  'eval'                    => array('doNotCopy'=>true, 'tl_class' => 'w50'),
  'foreignKey'              => 'tl_isotope_stock_account.title',
  'sql'                     => "int(10) unsigned NOT NULL default 0",
  'default'                 => '0',
];

$GLOBALS['TL_DCA']['tl_iso_shipping']['fields']['shipper_id'] = [
  'filter'                => true,
  'inputType'             => 'select',
  'foreignKey'            => \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel::getTable().'.name',
  'sql'                   => "int(10) unsigned NOT NULL default '0'",
  'eval'                  => array('mandatory'=>false, 'tl_class'=>'w50', 'chosen'=>true, 'includeBlankOption' => true),
  'relation'              => array('type'=>'hasOne', 'load'=>'lazy'),
];

$GLOBALS['TL_DCA']['tl_iso_shipping']['palettes']['__selector__'][] = 'isotopestock_override_store_account';
$GLOBALS['TL_DCA']['tl_iso_shipping']['subpalettes']['isotopestock_override_store_account'] = 'isotopestock_store_account';
$GLOBALS['TL_DCA']['tl_iso_shipping']['palettes']['combine_packaging_slip'] = '{title_legend},name,label,type;{note_legend:hide},note;{price_legend},price,tax_class,flatCalculation;{config_legend},countries,subdivisions,postalCodes,quantity_mode,minimum_quantity,maximum_quantity,minimum_total,maximum_total,minimum_weight,maximum_weight,product_types,product_types_condition,config_ids,address_type;{expert_legend:hide},guests,protected;{enabled_legend},enabled;{isotopestock_legend},isotopestock_override_store_account';
foreach($GLOBALS['TL_DCA']['tl_iso_shipping']['palettes'] as $palette_name => $palette) {
  if ($palette_name == '__selector__') {
    continue;
  }

  PaletteManipulator::create()
    ->addLegend('shipper_legend', 'enabled_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('shipper_id', 'shipper_legend', PaletteManipulator::POSITION_APPEND)
    ->addLegend('isotopestock_legend', 'enabled_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('isotopestock_override_store_account', 'isotopestock_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette($palette_name, 'tl_iso_shipping');
}
