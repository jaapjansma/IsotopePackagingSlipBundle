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

$GLOBALS['TL_DCA']['tl_member']['fields']['isotope_packaging_slip_on_hold'] = array(
  'filter'                  => true,
  'inputType'               => 'checkbox',
  'eval'                    => array('doNotCopy'=>true),
  'sql'                     => "char(1) NOT NULL default ''",
  'default'                 => '0',
  'save_callback'           => [
    ['Krabo\IsotopePackagingSlipBundle\Helper\MemberHelper', 'onSaveCallBack'],
  ]
);

PaletteManipulator::create()
  ->addLegend('isotope_packaging_slip_legend', 'login_legend', PaletteManipulator::POSITION_AFTER)
  ->addField('isotope_packaging_slip_on_hold', 'isotope_packaging_slip_legend', PaletteManipulator::POSITION_APPEND)
  ->applyToPalette('default', 'tl_member');