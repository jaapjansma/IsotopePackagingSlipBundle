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
use Krabo\IsotopePackagingSlipBundle\Helper\IsotopeHelper;
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
   * Copy the combined packaging slip from the source to the new cart.
   *
   * @param \Isotope\Model\ProductCollection\Order $draftOrder
   * @param \Isotope\Model\ProductCollection $cart
   * @param $arrItemIds
   *
   * @return void
   */
  public function updateDraftOrder(Order $draftOrder, ProductCollection $cart, $arrItemIds) {
    $draftOrder->combined_packaging_slip_id = $cart->combined_packaging_slip_id;
    $draftOrder->save();
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
        $packagingSlip->company = $order->getShippingAddress()->company;
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
        if (!$packagingSlip->scheduled_shipping_date || $scheduledShippingDate != $packagingSlip->scheduled_shipping_date) {
          $packagingSlip->scheduled_shipping_date = $scheduledShippingDate;
          $updatePackagingSlip = TRUE;
          if (!$packagingSlip->scheduled_picking_date || $scheduledPickingDate != $packagingSlip->scheduled_picking_date) {
            $packagingSlip->scheduled_picking_date = $scheduledPickingDate;
          }
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
    $shipper = null;
    if ($order->getShippingMethod()->shipper_id) {
      $shipper = IsotopePackagingSlipShipperModel::findByPk($order->getShippingMethod()->shipper_id);
    }
    $earliestScheduledShippingDate = IsotopeHelper::getScheduledShippingDate($order, $shipper);
    if ($order->scheduled_shipping_date && $order->scheduled_shipping_date > $earliestScheduledShippingDate) {
      return $order->scheduled_shipping_date;
    }
    return $earliestScheduledShippingDate;
  }

  /**
   * @param \Isotope\Model\ProductCollection\Order $order
   *
   * @return int|mixed|null
   */
  protected function getScheduledPickingDate(Order $order) {
    $shipper = null;
    if ($order->getShippingMethod()->shipper_id) {
      $shipper = IsotopePackagingSlipShipperModel::findByPk($order->getShippingMethod()->shipper_id);
    }
    $earliestScheduledShippingDate = IsotopeHelper::getScheduledShippingDate($order, $shipper);
    $date = new \DateTime();
    $date->setTimestamp($earliestScheduledShippingDate);
    $date->setTime(23, 59);
    $earliestScheduledShippingDate = $date->getTimestamp();
    $earliestPickingDate = IsotopeHelper::getScheduledPickingDate($order, $shipper);
    if ($order->scheduled_shipping_date && $order->scheduled_shipping_date > $earliestScheduledShippingDate) {
      return $order->scheduled_shipping_date;
    }
    return $earliestPickingDate;
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
    $arrTokens['packaging_slip_trackandtrace'] = '';
    $arrTokens['packaging_slip_trackandtrace_code'] = '';
    $arrTokens['packaging_slip_scheduled_shipping_date'] = '';
    $arrTokens['packaging_slip_shipper'] = '';
    $arrTokens['packaging_slip_shipping_date'] = '';
    $sql = "
      SELECT `tl_isotope_packaging_slip`.`id`, `tl_isotope_packaging_slip`.`shipping_date`, `tl_isotope_packaging_slip`.`scheduled_shipping_date`, `tl_isotope_packaging_slip_shipper`.`name` as `shipper`
      FROM `tl_isotope_packaging_slip`
      INNER JOIN `tl_isotope_packaging_slip_product_collection` ON `tl_isotope_packaging_slip_product_collection`.`pid` = `tl_isotope_packaging_slip`.`id`
      LEFT JOIN `tl_isotope_packaging_slip_shipper` ON `tl_isotope_packaging_slip_shipper`.`id` = `tl_isotope_packaging_slip`.`shipper_id`
      WHERE `tl_isotope_packaging_slip_product_collection`.`document_number` = ?
      ORDER BY `tl_isotope_packaging_slip`.`tstamp` DESC
      LIMIT 0, 1
    ";
    if ($order->scheduled_shipping_date) {
      $scheduledShippingDate = new \DateTime();
      $scheduledShippingDate->setTimestamp($order->scheduled_shipping_date);
      $arrTokens['packaging_slip_scheduled_shipping_date'] = $scheduledShippingDate->format('d-m-Y');
    }
    if ($order->getShippingMethod()->shipper_id) {
      $objShipper = IsotopePackagingSlipShipperModel::findByPk($order->getShippingMethod()->shipper_id);
      if ($objShipper) {
        $arrTokens['packaging_slip_shipper'] = $objShipper->name;
      }
    }
    $result = \Database::getInstance()->prepare($sql)->execute($order->document_number);
    if ($result) {
      if (!empty($result->shipping_date)) {
        $shippingDate = new \DateTime();
        $shippingDate->setTimestamp($result->shipping_date);
        $arrTokens['packaging_slip_shipping_date'] = $shippingDate->format('d-m-Y');
      }
      if (!empty($result->scheduled_shipping_date)) {
        $scheduledShippingDate = new \DateTime();
        $scheduledShippingDate->setTimestamp($result->scheduled_shipping_date);
        $arrTokens['packaging_slip_scheduled_shipping_date'] = $scheduledShippingDate->format('d-m-Y');
      }
      if (!empty($result->shipper)) {
        $arrTokens['packaging_slip_shipper'] = $result->shipper;
      }

      $packagingSlip = IsotopePackagingSlipModel::findByPk($result->id);
      if ($packagingSlip && $packagingSlip->getTrackAndTraceLink()) {
        $arrTokens['packaging_slip_trackandtrace'] = $packagingSlip->getTrackAndTraceLink();
      }
      if ($packagingSlip && $packagingSlip->getTrackAndTraceCode()) {
        $arrTokens['packaging_slip_trackandtrace_code'] = $packagingSlip->getTrackAndTraceCode();
      }
    }
    return $arrTokens;
  }

}
