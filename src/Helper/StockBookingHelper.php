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

use Contao\System;
use Isotope\Model\Config;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipProductCollectionModel;
use Krabo\IsotopeStockBundle\Event\BookingEvent;
use Krabo\IsotopeStockBundle\Event\Events;
use Krabo\IsotopeStockBundle\Helper\BookingHelper;
use Krabo\IsotopeStockBundle\Helper\ProductHelper;
use Krabo\IsotopeStockBundle\Model\AccountModel;
use Krabo\IsotopeStockBundle\Model\BookingLineModel;
use Krabo\IsotopeStockBundle\Model\BookingModel;
use Krabo\IsotopeStockBundle\Model\PeriodModel;

class StockBookingHelper {

  /**
   * Creates a new booking for a Product Collection Item
   *
   * @param IsotopePackagingSlipModel $packagingSlipModel
   * @param int $quantity
   * @param Product $product
   * @param string $documentNumber
   *
   * @return void
   */
  public static function createDeliveryBookingFromPackagingSlipAndProduct(IsotopePackagingSlipModel $packagingSlipModel, int $quantity, Product $product, string $documentNumber) {
    $config = IsotopeHelper::getConfig($packagingSlipModel);
    $debit_account = $config->isotopestock_order_credit_account;
    if ($product->isostock_preorder) {
      $debit_account = $config->isotopestock_preorder_credit_account;
    }

    $bookingType = BookingModel::DELIVERY_TYPE;
    self::clearBookingForPackagingSlipAndProduct($packagingSlipModel, $product->getId(), $bookingType, $documentNumber);
    $period = PeriodModel::getFirstActivePeriod();
    $booking = new BookingModel();
    $booking->description = $packagingSlipModel->getDocumentNumber();
    $booking->date = time();
    $booking->period = $period->id;
    $booking->product_id = $product->getId();
    $booking->type = $bookingType;
    $booking->packaging_slip_id = $packagingSlipModel->id;
    if ($documentNumber) {
      $order = Order::findOneBy('document_number', $documentNumber);
      $booking->order_id = $order->id;
    }
    $booking->save();
    $debitBookingLine = new BookingLineModel();
    $debitBookingLine->debit = $quantity;
    $debitBookingLine->account = $debit_account;
    $debitBookingLine->pid = $booking->id;
    $debitBookingLine->save();
    $creditBookingLine = new BookingLineModel();
    $creditBookingLine->credit = $quantity;
    $creditBookingLine->account = $packagingSlipModel->credit_account;
    $creditBookingLine->pid = $booking->id;
    $creditBookingLine->save();
    BookingHelper::updateBalanceStatusForBooking($booking->id);
    $event = new BookingEvent($booking);
    System::getContainer()
      ->get('event_dispatcher')
      ->dispatch($event, Events::BOOKING_EVENT);
  }

  /**
   * Creates a new booking for a Product Collection Item
   *
   * @param IsotopePackagingSlipModel $packagingSlipModel
   * @param IsotopePackagingSlipProductCollectionModel $product
   *
   * @return void
   */
  public static function createSalesBookingFromPackagingSlipAndProduct(IsotopePackagingSlipModel $packagingSlipModel, IsotopePackagingSlipProductCollectionModel $product) {
    $bookingType = BookingModel::SALES_TYPE;
    $documentNumber = $product->document_number;
    $config = IsotopeHelper::getConfig($packagingSlipModel);
    $debit_account = $config->isotopestock_order_debit_account;
    $credit_account = $config->isotopestock_order_credit_account;
    if ($product->getProduct()->isostock_preorder) {
      $credit_account = $config->isotopestock_preorder_credit_account;
    }
    self::clearBookingForPackagingSlipAndProduct($packagingSlipModel, $product->product_id, $bookingType, $documentNumber);
    $period = PeriodModel::getFirstActivePeriod();
    $booking = new BookingModel();
    $booking->description = $packagingSlipModel->getDocumentNumber();
    $booking->date = time();
    $booking->period = $period->id;
    $booking->product_id = $product->product_id;
    $booking->type = $bookingType;
    $booking->packaging_slip_id = $packagingSlipModel->id;
    if ($documentNumber) {
      $order = Order::findOneBy('document_number', $documentNumber);
      $booking->order_id = $order->id;
    }
    $booking->save();
    $debitBookingLine = new BookingLineModel();
    $debitBookingLine->debit = $product->quantity;
    $debitBookingLine->account = $debit_account;
    $debitBookingLine->pid = $booking->id;
    $debitBookingLine->save();
    $creditBookingLine = new BookingLineModel();
    $creditBookingLine->credit = $product->quantity;
    $creditBookingLine->account = $credit_account;
    $creditBookingLine->pid = $booking->id;
    $creditBookingLine->save();
    BookingHelper::updateBalanceStatusForBooking($booking->id);
    $event = new BookingEvent($booking);
    System::getContainer()
      ->get('event_dispatcher')
      ->dispatch($event, Events::BOOKING_EVENT);
  }

  /**
   * @param IsotopePackagingSlipModel $packagingSlipModel
   * @param $product_id
   * @param int $type
   * @param string $document_number
   */
  public static function clearBookingForPackagingSlipAndProduct(IsotopePackagingSlipModel $packagingSlipModel, $product_id, int $bookingType, string $document_number) {
    if (empty($document_number)) {
      \Database::getInstance()
        ->prepare("DELETE FROM `tl_isotope_stock_booking` WHERE `packaging_slip_id` = ? AND `product_id` = ? AND `type` = ? AND `order_id` = 0")
        ->execute($packagingSlipModel->id, $product_id, $bookingType);
    } else {
      $order = Order::findOneBy('document_number', $document_number);
      \Database::getInstance()
        ->prepare("DELETE FROM `tl_isotope_stock_booking` WHERE `packaging_slip_id` = ? AND `product_id` = ? AND `type` = ? AND `order_id` = ?")
        ->execute($packagingSlipModel->id, $product_id, $bookingType, $order->id);
    }
    self::clearBookingLines();
  }

  /**
   * @param IsotopePackagingSlipModel $packagingSlipModel
   * @param $product_id
   * @param int $type
   * @param string $document_number
   */
  public static function clearBookingForPackagingSlip(IsotopePackagingSlipModel $packagingSlipModel, int $bookingType) {
    \Database::getInstance()
      ->prepare("DELETE FROM `tl_isotope_stock_booking` WHERE `packaging_slip_id` = ? AND `type` = ?")
      ->execute($packagingSlipModel->id, $bookingType);
    self::clearBookingLines();
  }

  /**
   * @param IsotopePackagingSlipModel $packagingSlipModel
   * @param Order $order
   * @param int $type
   */
  public static function clearOrderBooking(Order $order, int $bookingType) {
    \Database::getInstance()
      ->prepare("DELETE FROM `tl_isotope_stock_booking` WHERE `packaging_slip_id` = 0 AND `type` = ? AND `order_id` = ?")
      ->execute($bookingType, $order->id);
    self::clearBookingLines();
  }

  /**
   * Clear the booking lines which are not connected to a booking
   *
   * @return void
   */
  public static function clearBookingLines() {
    \Database::getInstance()
      ->prepare("DELETE FROM `tl_isotope_stock_booking_line` WHERE `pid` NOT IN (SELECT `id` FROM `tl_isotope_stock_booking`)")
      ->execute();
  }

  /**
   * Returns list of products per store.
   *
   * @param array $ids
   *
   * @return array
   */
  public static function generateProductListForPackagingSlips(array $ids) {
    $return = [];
    foreach($ids as $id) {
      $packagingSlipModel = IsotopePackagingSlipModel::findByPk($id);
      $account = AccountModel::findByPk($packagingSlipModel->credit_account);
      if (!isset($return[$account->id])) {
        $return[$account->id] = [
          'label' => $account->title,
          'products' => [],
        ];
      }
      foreach($packagingSlipModel->getProductsCombinedByProductId() as $item) {
        $product = $item->getProduct();
        if (!$product) {
          continue;
        }
        if (!isset($return[$account->id]['products'][$product->id])) {
          $return[$account->id]['products'][$product->id] = [
            'quantity' => $item->quantity,
            'available' => ProductHelper::getProductCountPerAccount($product->id, $account->id),
            'sku' => $product->sku,
            'label' => $product->getName(),
            'package_slip_ids' => [$id],
          ];
        } else {
          $return[$account->id]['products'][$product->id]['quantity'] += $item->quantity;
          if (!in_array($id, $return[$account->id]['products'][$product->id]['package_slip_ids'])) {
            $return[$account->id]['products'][$product->id]['package_slip_ids'][] = $id;
          }
        }
      }
    }
    return $return;
  }

}