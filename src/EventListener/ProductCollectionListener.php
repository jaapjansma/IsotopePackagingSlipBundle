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

namespace Krabo\IsotopePackagingSlipBundle\EventListener;

use Isotope\Model\Config;
use Isotope\Model\OrderStatus;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel;

class ProductCollectionListener {

  /**
   * Copy the combined packaging slip from the source to the new cart.
   *
   * @param \Isotope\Model\ProductCollection $objCollection
   * @param \Isotope\Model\ProductCollection $objSource
   * @param $arrItemIds
   *
   * @return void
   */
  public function createFromProductCollection(ProductCollection $objCollection, ProductCollection $objSource, $arrItemIds) {
    $objCollection->combined_packaging_slip_id = $objSource->combined_packaging_slip_id;
  }

  /**
   * Add a packaging slip as soon as an order is paid
   *
   * @param \Isotope\Model\ProductCollection\Order $order
   * @param $intOldStatus
   * @param \Isotope\Model\OrderStatus $objNewStatus
   *
   * @return void
   */
  public function postOrderStatusUpdate(Order $order, $intOldStatus, OrderStatus $objNewStatus) {
    if ($order->isLocked() && $order->isCheckoutComplete() && !PackagingSlipModel::doesOrderExists($order->id)) {
      if (empty($order->combined_packaging_slip_id)) {
        $config = Config::findByPk($order->config_id);
        $prefix = $order->getConfig()->packagingSlipPrefix;
        if (empty($prefix)) {
          $prefix = $order->getConfig()->orderPrefix;
        }
        $orderSettings = unserialize($order->settings);
        $packagingSlip = new PackagingSlipModel();
        $packagingSlip->date = time();
        if ($order->member) {
          $packagingSlip->member = $order->member;
        }
        $packagingSlip->firstname = $order->getShippingAddress()->firstname;
        $packagingSlip->lastname = $order->getShippingAddress()->lastname;
        $packagingSlip->housenumber = $order->getShippingAddress()->housenumber;
        $packagingSlip->street_1 = $order->getShippingAddress()->street_1;
        $packagingSlip->street_2 = $order->getShippingAddress()->street_2;
        $packagingSlip->street_3 = $order->getShippingAddress()->street_3;
        $packagingSlip->postal = $order->getShippingAddress()->postal;
        $packagingSlip->city = $order->getShippingAddress()->city;
        $packagingSlip->country = $order->getShippingAddress()->country;
        $packagingSlip->notes = $orderSettings['email_data']['form_opmerking'];
        $packagingSlip->shipping_id = $order->getShippingMethod()->getId();
        $packagingSlip->config_id = $order->config_id;
        $packagingSlip->debit_account = $config->isotopestock_order_credit_account;
        $packagingSlip->credit_account = $config->isotopestock_store_account;
        if ($order->getShippingMethod()->isotopestock_override_store_account) {
          $packagingSlip->credit_account = $order->getShippingMethod()->isotopestock_store_account;
        }
        $packagingSlip->save();
        $orderDigits = (int) $order->getConfig()->orderDigits;
        $packagingSlip->generateDocumentNumber($prefix, $orderDigits);
      } else {
        $packagingSlip = PackagingSlipModel::findOneBy('document_number', $order->combined_packaging_slip_id);
      }
      PackagingSlipModel::addOrder($packagingSlip->id, $order->id);
      $products = [];
      foreach($packagingSlip->getProducts() as $product_id => $item) {
        $products[$product_id] = $item['quantity'];
      }
      PackagingSlipModel::saveProducts($packagingSlip->id, $products);
    }
  }

}