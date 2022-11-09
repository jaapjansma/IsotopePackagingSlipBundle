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

use Isotope\Model\Config;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Order;
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
      if ($objProduct && $objProduct->isostock_preorder && $objProduct->isotope_packaging_slip_scheduled_picking_date && $objProduct->isotope_packaging_slip_scheduled_picking_date > $scheduledDate) {
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
      if ($objProduct && $objProduct->isostock_preorder && $objProduct->isotope_packaging_slip_scheduled_shipping_date && $objProduct->isotope_packaging_slip_scheduled_shipping_date > $scheduledDate) {
        $scheduledDate = $objProduct->isotope_packaging_slip_scheduled_shipping_date;
      }
    }
    return $scheduledDate;
  }

}