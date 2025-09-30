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

namespace Krabo\IsotopePackagingSlipBundle\Helper;

use Isotope\Interfaces\IsotopeAttribute;
use Isotope\Model\Config;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\ProductCollectionItem;
use JvH\IsotopeMyOrdersBundle\Helper\PackagingSlip;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel;

class IsotopeHelper {

  /**
   * @param \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel $packagingSlipModel
   *
   * @return \Isotope\Model\Config
   */
  public static function getConfig(IsotopePackagingSlipModel $packagingSlipModel): Config {
    $config = null;
    $defaultConfig = \Isotope\Isotope::getConfig();
    if (!empty($packagingSlipModel->config_id)) {
      $config = \Isotope\Model\Config::findByPk($packagingSlipModel->config_id);
    }
    if (empty($config)) {
      $config = $defaultConfig;
    }
    return $config;
  }

  /**
   * @param \Isotope\Model\ProductCollection\ProductCollection $order
   * @param \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel $shipper=null
   *
   * @return int|mixed|null
   */
  public static function getScheduledPickingDate(ProductCollection $productCollection, IsotopePackagingSlipShipperModel $shipper=null) {
    $scheduledDate = time();
    if ($shipper && $shipper->isotope_packaging_slip_scheduled_picking_date && $shipper->isotope_packaging_slip_scheduled_picking_date > $scheduledDate) {
      $scheduledDate = $shipper->isotope_packaging_slip_scheduled_picking_date;
    }
    foreach($productCollection->getItems() as $objItem) {
      $objProduct = $objItem->getProduct();
      if ($objProduct && $objProduct->isotope_packaging_slip_scheduled_picking_date && $objProduct->isotope_packaging_slip_scheduled_picking_date > $scheduledDate) {
        $scheduledDate = $objProduct->isotope_packaging_slip_scheduled_picking_date;
      }
    }
    return $scheduledDate;
  }

  /**
   * @param \Isotope\Model\ProductCollection\ProductCollection $order
   * @param \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel $shipper=null
   *
   * @return int|mixed|null
   */
  public static function getScheduledShippingDate(ProductCollection $productCollection, IsotopePackagingSlipShipperModel $shipper=null) {
    $scheduledDate = time();
    if ($shipper && $shipper->isotope_packaging_slip_scheduled_shipping_date && $shipper->isotope_packaging_slip_scheduled_shipping_date > $scheduledDate) {
      $scheduledDate = $shipper->isotope_packaging_slip_scheduled_shipping_date;
    }
    foreach($productCollection->getItems() as $objItem) {
      $objProduct = $objItem->getProduct();
      if ($objProduct && $objProduct->isotope_packaging_slip_scheduled_shipping_date && $objProduct->isotope_packaging_slip_scheduled_shipping_date > $scheduledDate) {
        $scheduledDate = $objProduct->isotope_packaging_slip_scheduled_shipping_date;
      }
    }
    if ($productCollection->combined_packaging_slip_id) {
      $packagingSlip = IsotopePackagingSlipModel::findOneBy('document_number', $productCollection->combined_packaging_slip_id);
      foreach($packagingSlip->getProductsCombinedByProductId() as $objItem) {
        $objProduct = $objItem->getProduct();
        if ($objProduct && $objProduct->isotope_packaging_slip_scheduled_shipping_date && $objProduct->isotope_packaging_slip_scheduled_shipping_date > $scheduledDate) {
          $scheduledDate = $objProduct->isotope_packaging_slip_scheduled_shipping_date;
        }
      }
    }
    return $scheduledDate;
  }

  /**
   * @param \Isotope\Model\ProductCollection\ProductCollection $order
   * @param \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel $shipper=null
   *
   * @return int|mixed|null
   */
  public static function getScheduledShippingDateForPackagingSlip(IsotopePackagingSlipModel $packagingSlip) {
    $scheduledDate = time();
    $shipper = null;
    if ($packagingSlip->shipper_id) {
      $shipper = IsotopePackagingSlipShipperModel::findByPk($packagingSlip->shipper_id);
    }
    if ($shipper && $shipper->isotope_packaging_slip_scheduled_shipping_date && $shipper->isotope_packaging_slip_scheduled_shipping_date > $scheduledDate) {
      $scheduledDate = $shipper->isotope_packaging_slip_scheduled_shipping_date;
    }
    foreach($packagingSlip->getProductsCombinedByProductId() as $objItem) {
      $objProduct = $objItem->getProduct();
      if ($objProduct && $objProduct->isotope_packaging_slip_scheduled_shipping_date && $objProduct->isotope_packaging_slip_scheduled_shipping_date > $scheduledDate) {
        $scheduledDate = $objProduct->isotope_packaging_slip_scheduled_shipping_date;
      }
    }
    return $scheduledDate;
  }

  public static function generateOptions(ProductCollectionItem $item): string {
    $options = [];
    foreach($item->getOptions() as $strAttribute => $value) {
      if ($value) {
        $options[] = self::generateAttribute($strAttribute, $value, ['html' => false, 'item' => $item]);
      }
    }
    return implode("\n", $options);
  }

  public static function generateAttribute($strAttribute,$value, array $options = array())
  {
    $objAttribute = $GLOBALS['TL_DCA']['tl_iso_product']['attributes'][$strAttribute];
    if (!($objAttribute instanceof IsotopeAttribute)) {
      return '';
    }
    $generatedValue = $objAttribute->generateValue($value, $options);
    $re = '/(\(€.*\))/m';
    $subst = "";
    $generatedValue = trim(preg_replace($re, $subst, $generatedValue));
    return $generatedValue;
  }

}