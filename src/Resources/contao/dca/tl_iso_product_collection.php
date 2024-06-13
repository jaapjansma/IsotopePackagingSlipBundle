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

use Contao\Image;
use Contao\StringUtil;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_iso_product_collection']['config']['sql']['keys']['document_number'] = 'index';
$GLOBALS['TL_DCA']['tl_iso_product_collection']['config']['sql']['keys']['type,document_number'] = 'index';

$GLOBALS['TL_DCA']['tl_iso_product_collection']['list']['operations']['packaging_slips'] = [
  'label'             => &$GLOBALS['TL_LANG']['tl_iso_product_collection']['packaging_slips'],
  'href'              => 'href=' . \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel::getTable(),
  'icon'              => 'bundles/isotopepackagingslip/price-tag.png',
  'button_callback'   => ['tl_iso_product_collection_packaging_slip', 'packagingSlipButton'],
];

$GLOBALS['TL_DCA']['tl_iso_product_collection']['fields']['scheduled_shipping_date'] = [
  'exclude'               => true,
  'inputType'             => 'text',
  'eval'                  => array('rgxp'=>'datim', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
  'sql'                   => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_iso_product_collection']['fields']['combined_packaging_slip_id'] = array(
  'exclude'       => true,
  'search'        => true,
  'readonly'        => true,
  'inputType'     => 'text',
  'eval'          => array( 'mandatory'=>false, 'tl_class'=>'w50 clr' ),
  'sql'           => "varchar(64) NOT NULL default ''",
);

PaletteManipulator::create()
  ->addField('scheduled_shipping_date', 'date_shipped')
  ->applyToPalette('default', 'tl_iso_product_collection');

class tl_iso_product_collection_packaging_slip {

  public function packagingSlipButton($arrData, $href, $strLabel, $strTitle, $strIcon, $strHtmlAttrs, $strTable, $rootIds, $childIds, $isCircular, $prevLabel, $nextLabel, \Contao\DataContainer $dc) {
    $packagingSlipDocumentNumbers = [];
    $order = \Isotope\Model\ProductCollection\Order::findByPk($arrData['id']);
    $url = \Contao\Backend::addToUrl($href);
    if ($order) {
      $packagingSlips = \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel::findPackagingSlipsByOrder($order);
      if ($packagingSlips) {
        foreach ($packagingSlips as $packagingSlip) {
          $packagingSlipDocumentNumbers[] = $packagingSlip->document_number;
        }
        $strTitle = sprintf($GLOBALS['TL_LANG']['tl_iso_product_collection']['packaging_slips'][1], implode(", ", $packagingSlipDocumentNumbers));
        $url = \Contao\Backend::addToUrl($href . '&amp;do=tl_isotope_packaging_slip&amp;order_id=' . $arrData['id']);
        return '<a href="' . $url . '" title="' . StringUtil::specialchars($strTitle) . '">' . Image::getHtml($strIcon, $strTitle, 'style="width: 16px; height: 16px;"') . '</a> ';
      }
    }
    return '';
  }

}