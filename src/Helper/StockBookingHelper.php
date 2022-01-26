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

use Isotope\Model\Product;
use Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel;
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
   * @param PackagingSlipModel $packagingSlipModel
   * @param int $quantity
   * @param \Isotope\Model\ProductCollectionItem $item
   * @param int $debit_account_id
   * @param int $crebit_account_id
   * @param int $bookingType
   *
   * @return void
   */
  public static function createBookingFromPackagingSlipAndProduct(PackagingSlipModel $packagingSlipModel, $quantity, Product $product) {
    $bookingType = BookingModel::DELIVERY_TYPE;
    self::clearBookingForPackagingSlipAndProduct($packagingSlipModel, $product->getId(), $bookingType);
    $period = PeriodModel::getFirstActivePeriod();
    $booking = new BookingModel();
    $booking->description = $packagingSlipModel->document_number;
    $booking->date = time();
    $booking->period = $period->id;
    $booking->product_id = $product->getId();
    $booking->type = $bookingType;
    $booking->packaging_slip_id = $packagingSlipModel->id;
    $booking->save();
    $debitBookingLine = new BookingLineModel();
    $debitBookingLine->debit = $quantity;
    $debitBookingLine->account = $packagingSlipModel->debit_account;
    $debitBookingLine->pid = $booking->id;
    $debitBookingLine->save();
    $creditBookingLine = new BookingLineModel();
    $creditBookingLine->credit = $quantity;
    $creditBookingLine->account = $packagingSlipModel->credit_account;
    $creditBookingLine->pid = $booking->id;
    $creditBookingLine->save();
    BookingHelper::updateBalanceStatusForBooking($booking->id);
  }

  /**
   * @param PackagingSlipModel $packagingSlipModel
   * @param $product_id
   * @param int $type
   */
  public static function clearBookingForPackagingSlipAndProduct(PackagingSlipModel $packagingSlipModel, $product_id, int $bookingType) {
    \Database::getInstance()
      ->prepare("DELETE FROM `tl_isotope_stock_booking` WHERE `packaging_slip_id` = ? AND `product_id` = ? AND `type` = ?")
      ->execute($packagingSlipModel->id, $product_id, $bookingType);
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
      $packagingSlipModel = PackagingSlipModel::findByPk($id);
      $account = AccountModel::findByPk($packagingSlipModel->credit_account);
      if (!isset($return[$account->id])) {
        $return[$account->id] = [
          'label' => $account->title,
          'products' => [],
        ];
      }
      foreach($packagingSlipModel->getProducts() as $item) {
        $product = $item['product'];
        if (!isset($return[$account->id]['products'][$product->id])) {
          $return[$account->id]['products'][$product->id] = [
            'quantity' => $item['quantity'],
            'available' => ProductHelper::getProductCountPerAccount($product->id, $account->id),
            'sku' => $product->sku,
            'label' => $product->getName(),
            'package_slip_ids' => [$id],
          ];
        } else {
          $return[$account->id]['products'][$product->id]['quantity'] += $item['quantity'];
          if (!in_array($id, $return[$account->id]['products'][$product->id]['quantity']['package_slip_ids'])) {
            $return[$account->id]['products'][$product->id]['quantity']['package_slip_ids'][] = $id;
          }
        }
      }
    }
    return $return;
  }

}