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

use Contao\MemberModel;
use Contao\System;
use Isotope\Model\Config;
use Isotope\Model\OrderStatus;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Event\Events;
use Krabo\IsotopePackagingSlipBundle\Event\PackagingSlipOrderEvent;
use Krabo\IsotopePackagingSlipBundle\Helper\PackagingSlipCheckAvailability;
use Krabo\IsotopePackagingSlipBundle\Helper\StockBookingHelper;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipProductCollectionModel;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel;
use Krabo\IsotopeStockBundle\Model\BookingModel;

class ProductCollectionListener {

  /**
   * @var null|bool
   */
  protected $currentOrderPaidStatus = null;

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
   * @param \Isotope\Model\ProductCollection\Order $order
   * @param \Isotope\Model\OrderStatus $newStatus
   * @param $changes
   *
   * @return void
   */
  public function preOrderStatusUpdate(Order $order, OrderStatus $newStatus, $changes) {
    $this->currentOrderPaidStatus = null;
    if ($order->isLocked() && $order->isCheckoutComplete()) {
      $this->currentOrderPaidStatus = $order->isPaid();
    }
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
    if ($order->isLocked() && $order->isCheckoutComplete() && !IsotopePackagingSlipModel::doesOrderExists($order)) {
      $orderSettings = unserialize($order->settings);
      if (empty($order->combined_packaging_slip_id)) {
        $eventName = Events::PACKAGING_SLIP_CREATED_FROM_ORDER;
        $config = Config::findByPk($order->config_id);
        $prefix = $order->getConfig()->packagingSlipPrefix;
        if (empty($prefix)) {
          $prefix = $order->getConfig()->orderPrefix;
        }
        $packagingSlip = new IsotopePackagingSlipModel();
        $packagingSlip->tstamp = time();
        $packagingSlip->date = time();
        $packagingSlip->scheduled_shipping_date = $this->getScheduledShippingDate($order);
        $packagingSlip->scheduled_picking_date = $this->getScheduledPickingDate($order);
        $packagingSlip->status = '0';
        if ($order->member) {
          $packagingSlip->member = $order->member;
          $objMember = MemberModel::findByPk($order->member);
          if ($objMember->isotope_packaging_slip_on_hold) {
            $packagingSlip->status = '-1';
          }
        }
        $packagingSlip->firstname = $order->getShippingAddress()->firstname;
        $packagingSlip->lastname = $order->getShippingAddress()->lastname;
        $packagingSlip->email = $order->getShippingAddress()->email;
        $packagingSlip->phone = $order->getShippingAddress()->phone;
        $packagingSlip->housenumber = $order->getShippingAddress()->housenumber;
        $packagingSlip->street_1 = $order->getShippingAddress()->street_1;
        $packagingSlip->street_2 = $order->getShippingAddress()->street_2;
        $packagingSlip->street_3 = $order->getShippingAddress()->street_3;
        $packagingSlip->postal = $order->getShippingAddress()->postal;
        $packagingSlip->city = $order->getShippingAddress()->city;
        $packagingSlip->country = $order->getShippingAddress()->country;
        if (!empty($orderSettings['email_data']['form_opmerking'])) {
          $packagingSlip->notes = $orderSettings['email_data']['form_opmerking'];
        }
        $packagingSlip->shipping_id = $order->getShippingMethod()->getId();
        $packagingSlip->config_id = $order->config_id;
        $packagingSlip->debit_account = $config->isotopestock_order_credit_account;
        $packagingSlip->credit_account = $config->isotopestock_store_account;
        if ($order->getShippingMethod()->isotopestock_override_store_account) {
          $packagingSlip->credit_account = $order->getShippingMethod()->isotopestock_store_account;
        }
        if ($order->getShippingMethod()->shipper_id) {
          $packagingSlip->shipper_id = $order->getShippingMethod()->shipper_id;
        }
        $packagingSlip->save();
        $orderDigits = (int) $order->getConfig()->orderDigits;
        $packagingSlip->generateDocumentNumber($prefix, $orderDigits);

        $products = $this->addProductsFromOrder($packagingSlip, $order);
        IsotopePackagingSlipProductCollectionModel::saveProducts($packagingSlip, $products);

        $event = new PackagingSlipOrderEvent($packagingSlip, $order);
        System::getContainer()->get('event_dispatcher')->dispatch($event, $eventName);
      } else {
        $updatePackagingSlip = false;
        $packagingSlip = IsotopePackagingSlipModel::findOneBy('document_number', $order->combined_packaging_slip_id);
        if (!empty($orderSettings['email_data']['form_opmerking'])) {
          $packagingSlip->notes .= "\r\n\r\n" . $orderSettings['email_data']['form_opmerking'];
          $updatePackagingSlip = true;
        }
        $scheduledShippingDate = $this->getScheduledShippingDate($order);
        $scheduledPickingDate = $this->getScheduledPickingDate($order);
        if (!$packagingSlip->scheduled_shipping_date || $scheduledShippingDate > $packagingSlip->scheduled_shipping_date) {
          $packagingSlip->scheduled_shipping_date = $scheduledShippingDate;
          $updatePackagingSlip = true;
        }
        if (!$packagingSlip->scheduled_picking_date || $scheduledPickingDate > $packagingSlip->scheduled_picking_date) {
          $packagingSlip->scheduled_picking_date = $scheduledPickingDate;
          $updatePackagingSlip = true;
        }
        if ($updatePackagingSlip) {
          $packagingSlip->save();
        }

        $products = $this->addProductsFromOrder($packagingSlip, $order);
        IsotopePackagingSlipProductCollectionModel::saveProducts($packagingSlip, $products);
      }
    } elseif ($order->isLocked() && $order->isCheckoutComplete() && $this->currentOrderPaidStatus !== null && $this->currentOrderPaidStatus != $order->isPaid()) {
      $packagingSlips = IsotopePackagingSlipModel::findPackagingSlipsByOrder($order);
      $arrIds = [];
      foreach($packagingSlips as $packagingSlip) {
        if ($packagingSlip->status == 0) {
          $arrIds[] = $packagingSlip->id;
        }
      }
      if (count($arrIds)) {
        PackagingSlipCheckAvailability::resetAvailabilityStatus($arrIds);
      }
    }

    StockBookingHelper::clearOrderBooking($order, BookingModel::SALES_TYPE);
  }

  /**
   * Add Products from a specific order to the list of products.
   *
   * @param IsotopePackagingSlipModel $packagingSlip
   * @param Order $order
   *
   * @return array
   */
  protected function addProductsFromOrder(IsotopePackagingSlipModel $packagingSlip, Order $order) {
    $arrProducts = [];
    $productCollection = IsotopePackagingSlipProductCollectionModel::findBy('pid', $packagingSlip->id);
    if ($productCollection) {
      $arrProducts = $productCollection->getModels();
    }
    $db = \Database::getInstance();
    $objResults = $db->prepare("
        SELECT `product_id`, `quantity`, `price` 
        FROM `tl_iso_product_collection_item` 
        WHERE `pid` = ?
    ")->execute($order->id);
    while ($objResults->next()) {
      $product = new IsotopePackagingSlipProductCollectionModel();
      $product->pid = $packagingSlip->pid;
      $product->product_id = $objResults->product_id;
      $product->quantity = $objResults->quantity;
      $product->document_number = $order->document_number;
      $product->value = $objResults->quantity * $objResults->price;
      $arrProducts[] = $product;
    }
    return $arrProducts;
  }

  /**
   * @param \Isotope\Model\ProductCollection\Order $order
   *
   * @return int|mixed|null
   */
  protected function getScheduledShippingDate(Order $order) {
    $scheduledDate = strtotime('+1 day');
    if ($order->getShippingMethod()->shipper_id) {
      $shipper = IsotopePackagingSlipShipperModel::findByPk($order->getShippingMethod()->shipper_id);
      if ($shipper->isotope_packaging_slip_scheduled_shipping_date && $shipper->isotope_packaging_slip_scheduled_shipping_date > $scheduledDate) {
        $scheduledDate = $shipper->isotope_packaging_slip_scheduled_shipping_date;
      }
    }
    foreach($order->getItems() as $objItem) {
      $objProduct = $objItem->getProduct();
      if ($objProduct && $objProduct->isostock_preorder && $objProduct->isotope_packaging_slip_scheduled_shipping_date && $objProduct->isotope_packaging_slip_scheduled_shipping_date > $scheduledDate) {
        $scheduledDate = $objProduct->isotope_packaging_slip_scheduled_shipping_date;
      }
    }
    return $scheduledDate;
  }

  /**
   * @param \Isotope\Model\ProductCollection\Order $order
   *
   * @return int|mixed|null
   */
  protected function getScheduledPickingDate(Order $order) {
    $scheduledDate = time();
    if ($order->getShippingMethod()->shipper_id) {
      $shipper = IsotopePackagingSlipShipperModel::findByPk($order->getShippingMethod()->shipper_id);
      if ($shipper->isotope_packaging_slip_scheduled_picking_date && $shipper->isotope_packaging_slip_scheduled_picking_date > $scheduledDate) {
        $scheduledDate = $shipper->isotope_packaging_slip_scheduled_picking_date;
      }
    }
    foreach($order->getItems() as $objItem) {
      $objProduct = $objItem->getProduct();
      if ($objProduct && $objProduct->isostock_preorder && $objProduct->isotope_packaging_slip_scheduled_picking_date && $objProduct->isotope_packaging_slip_scheduled_picking_date > $scheduledDate) {
        $scheduledDate = $objProduct->isotope_packaging_slip_scheduled_picking_date;
      }
    }
    return $scheduledDate;
  }

  /**
   * Add the DHL Tracker Code
   *
   * @param \Isotope\Model\ProductCollection\Order $order
   * @param $arrTokens
   *
   * @return mixed
   */
  public function getOrderNotificationTokens(ProductCollection\Order $order, &$arrTokens) {
    $sql = "
      SELECT `shipping_date`
      FROM `tl_isotope_packaging_slip`
      INNER JOIN `tl_isotope_packaging_slip_product_collection` ON `tl_isotope_packaging_slip_product_collection`.`pid` = `tl_isotope_packaging_slip`.`id`
      WHERE `tl_isotope_packaging_slip_product_collection`.`document_number` = ?
      AND `shipping_date` != ''
      ORDER BY `tl_isotope_packaging_slip`.`tstamp` DESC
      LIMIT 0, 1
    ";
    $result = \Database::getInstance()->prepare($sql)->execute($order->document_number);
    if ($result) {
      $shippingDate = new \DateTime();
      $shippingDate->setTimestamp($result->shipping_date);
      $arrTokens['packaging_slip_shipping_date'] = $shippingDate->format('d-m-Y');
    }
    return $arrTokens;
  }

}