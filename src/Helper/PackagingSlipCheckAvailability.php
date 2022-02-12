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
use Symfony\Component\HttpFoundation\Session\Session;
use Isotope\Model\ProductCollection\Order;

class PackagingSlipCheckAvailability {

  public static function checkAvailability(\Contao\DataContainer $dc) {
    /** @var Session $objSession */
    $objSession = System::getContainer()->get('session');
    // Get current IDs from session
    $session = $objSession->all();
    $ids = $session['CURRENT']['IDS'];
    static::checkAvailabilityForPackagingSlips($ids);
  }

  /**
   * @return void
   */
  public static function checkAllOpenForAvailability() {
    $db = \Database::getInstance();
    $ids = $db->execute("SELECT `id` FROM `tl_isotope_packaging_slip` WHERE `status` = '0'")->fetchEach('id');
    static::checkAvailabilityForPackagingSlips($ids);
  }

  /**
   * @param array $ids
   *
   * @return void
   */
  public static function checkAvailabilityForPackagingSlips(array $ids) {
    if (count($ids)) {
      $strIds = implode(",", $ids);
      $db = \Database::getInstance();
      // Clear current state.
      $db->execute("UPDATE `tl_isotope_packaging_slip` SET `is_available` = '0', `availability_notes` = '' WHERE `id` IN ({$strIds})");
      $products = StockBookingHelper::generateProductListForPackagingSlips($ids);
      foreach ($products as $store) {
        foreach ($store['products'] as $product) {
          if ($product['quantity'] && $product['quantity'] > $product['available']) {
            $strPackageSlipIds = implode(",", $product['package_slip_ids']);
            $note = sprintf($GLOBALS['TL_LANG']['MSC']['PackageSlipProductNotAvailable'], $product['label']);
            $db->prepare("UPDATE `tl_isotope_packaging_slip` SET `is_available` = '-1', `availability_notes` = TRIM(CONCAT(COALESCE(`availability_notes`), ' ', ?)) WHERE `id` IN ({$strPackageSlipIds})")->execute($note);
          }
        }
      }

      $result = $db->execute("SELECT `document_number`, `pid` FROM `tl_isotope_packaging_slip_product_collection` WHERE `document_number` != '' AND `pid` IN ($strIds) GROUP BY `document_number`, `pid`");
      while($result->next()) {
        /** @var Order $order */
        $order = Order::findOneBy('document_number', $result->document_number);
        if (!$order->isPaid()) {
          $note = sprintf($GLOBALS['TL_LANG']['MSC']['PackageSlipOrderNotPaid'], $order->document_number);
          $strPackageSlipIds = $result->pid;
          $db->prepare("UPDATE `tl_isotope_packaging_slip` SET `is_available` = '-1', `availability_notes` = TRIM(CONCAT(COALESCE(`availability_notes`), ' ', ?)) WHERE `id` IN ({$strPackageSlipIds})")->execute([$note]);
        }
      }

      $db->execute("UPDATE `tl_isotope_packaging_slip` SET `is_available` = '1' WHERE `id` IN ({$strIds}) AND `is_available` = '0'");
    }
  }

}