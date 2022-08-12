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

use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\System;
use DateTime;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Symfony\Component\HttpFoundation\Session\Session;

class PackagingSlipCheckAvailability {

  /**
   * @return void
   */
  public static function checkAvailability() {
    $db = System::importStatic('Database');
    /** @var Session $objSession */
    $objSession = System::getContainer()->get('session');
    // Get current IDs from session
    $session = $objSession->all();
    static::resetAvailabilityStatus($session['CURRENT']['IDS']);
  }

  /**
   * Check for product availability.
   *
   * @param int $maximumNumberOfProductsToCheck
   * @return void
   */
  public static function checkProductAvailability(int $maximumNumberOfProductsToCheck=25) {
    // @ ToDo set product is_available to 0 (unknown) at certain point.
    $db = System::importStatic('Database');
    $today = new DateTime();
    $productSql = "
        SELECT `packaging_slip_product`.`product_id`, SUM(`packaging_slip_product`.`quantity`) AS `quantity`, `packaging_slip`.`credit_account`, `packaging_slip`.`debit_account` 
        FROM `tl_isotope_packaging_slip_product_collection` `packaging_slip_product`
        INNER JOIN `tl_isotope_packaging_slip` `packaging_slip`
        WHERE  `packaging_slip`.`status` = '0' AND (`packaging_slip`.`scheduled_shipping_date` = '' OR `packaging_slip`.`scheduled_shipping_date` <= ?)
        AND `packaging_slip_product`.`is_available` = '0'
        GROUP BY `product_id`, `packaging_slip`.`credit_account`, `packaging_slip`.`debit_account`
        ORDER BY `product_id` ASC, `quantity` ASC
        LIMIT 0, ?";
    $updateProductSql = "
        UPDATE `tl_isotope_packaging_slip_product_collection` `packaging_slip_product`
        INNER JOIN `tl_isotope_packaging_slip` `packaging_slip`
        SET `packaging_slip_product`.`is_available` = ?
        WHERE `packaging_slip_product`.`product_id` = ? AND `packaging_slip`.`credit_account` = ? AND `packaging_slip`.`debit_account` = ? 
        AND `packaging_slip`.`status` = '0' AND `packaging_slip`.`check_availability` = '1' AND (`packaging_slip`.`scheduled_shipping_date` = '' OR `packaging_slip`.`scheduled_shipping_date` <= ?)";
    $objResult = $db->prepare($productSql)->execute($today->getTimestamp(), $maximumNumberOfProductsToCheck);
    while ($objResult->next()) {
      // How much are available in the credit account (Magazijn)
      $creditCount = ProductStockHelper::getProductCountPerAccount($objResult->product_id, $objResult->credit_account);
      $debitCount = ProductStockHelper::getProductCountPerAccountAndPackagingSlipStatus($objResult->product_id, $objResult->debit_account, IsotopePackagingSlipModel::STATUS_PREPARE_FOR_SHIPPING);
      if ($creditCount - $debitCount >= 0) {
        // Product is available, there are more in stock then there are reserved for prepared for shipping
        $db->prepare($updateProductSql)->execute('1', $objResult->product_id, $objResult->credit_account, $objResult->debit_account, $today->getTimestamp());
      } else {
        // Product is not available.
        $db->prepare($updateProductSql)->execute('-1', $objResult->product_id, $objResult->credit_account, $objResult->debit_account, $today->getTimestamp());
      }
    }
  }

  /**
   * @param array $packagingSlipIds
   *
   * @return void
   */
  public static function resetAvailabilityStatus(array $packagingSlipIds) {
    $db = System::importStatic('Database');
    $strIds = implode(",", $packagingSlipIds);

    $updatePackagingSlipProductSql = "
      UPDATE `tl_isotope_packaging_slip_product_collection` 
      SET `is_available` = '0'
      WHERE `pid` IN (" . $strIds . ")
    ";

    $updatePackagingSlipSql = "
      UPDATE `tl_isotope_packaging_slip` 
      SET `check_availability` = '1', `is_available` = '0', `availability_notes` = '' 
      WHERE `id` IN (" . $strIds . ") 
    ";

    if ($strIds) {
      $db->prepare($updatePackagingSlipSql)->execute();
      $db->prepare($updatePackagingSlipProductSql)->execute();
    }
  }

  /**
   * @param int $productId
   *
   * @return void
   */
  public static function resetAvailabilityStatusPerProduct(int $productId) {
    $db = System::importStatic('Database');

    $updatePackagingSlipProductSql = "
      UPDATE `tl_isotope_packaging_slip_product_collection` 
      SET `is_available` = '0'
      WHERE `product_id` = ?
      AND `pid` IN (SELECT `id` FROM `tl_isotope_packaging_slip` WHERE `status` = 0)
    ";

    $updatePackagingSlipSql = "
      UPDATE `tl_isotope_packaging_slip` 
      SET `check_availability` = '1', `is_available` = '0', `availability_notes` = '' 
      WHERE `id` IN (SELECT `pid` FROM `tl_isotope_packaging_slip_product_collection` WHERE `product_id` = ?)
      AND `status` = 0
    ";

    $db->prepare($updatePackagingSlipSql)->execute($productId);
    $db->prepare($updatePackagingSlipProductSql)->execute($productId);
  }

  /**
   * Check packaging slip
   * and set check_availability to 0
   * and update the is_available statis according to whether the product are available and whether the order should be paid.
   *
   * @param int $maximumNumberOfPackagingSlipsToCheck
   *
   * @return void
   */
  public static function checkPackagingSlips(int $maximumNumberOfPackagingSlipsToCheck=50) {
    $db = System::importStatic('Database');

    // Update not in stock.
    $updateNotInStockSql = "
      UPDATE `tl_isotope_packaging_slip` SET `check_availability` = '0', `is_available` = '-1', `availability_notes` = ?
      WHERE `id` IN (SELECT `pid` FROM `tl_isotope_packaging_slip_product_collection` WHERE `is_available` = '-1')
      AND `status` = '0' AND `check_availability` = '1'  
    ";
    $note = $GLOBALS['TL_LANG']['MSC']['PackageSlipProductNotAvailable'];
    $db->prepare($updateNotInStockSql)->execute($note);

    $updatePackagingSlipSqlWithNoCheckForPayment = "
      UPDATE `tl_isotope_packaging_slip`
      LEFT JOIN `tl_isotope_packaging_slip_shipper` ON `tl_isotope_packaging_slip_shipper`.`id` = `tl_isotope_packaging_slip`.`shipper_id`
      SET `tl_isotope_packaging_slip`.`check_availability` = '0', `tl_isotope_packaging_slip`.`is_available` = '1', `tl_isotope_packaging_slip`.`availability_notes` = ''
      WHERE `tl_isotope_packaging_slip`.`id` NOT IN (SELECT `pid` FROM `tl_isotope_packaging_slip_product_collection` WHERE `tl_isotope_packaging_slip_product_collection`.`is_available` IN ('0', '-1'))
      AND `status` = '0' AND `check_availability` = '1' AND (`tl_isotope_packaging_slip_shipper`.`id` IS NULL OR `tl_isotope_packaging_slip_shipper`.`handle_only_paid` = '0')
    ";
    $db->prepare($updatePackagingSlipSqlWithNoCheckForPayment)->execute();

    $packagingSlipSql = "
      SELECT `tl_isotope_packaging_slip`.`id`, `tl_isotope_packaging_slip_shipper`.`handle_only_paid`
      FROM `tl_isotope_packaging_slip`
      LEFT JOIN `tl_isotope_packaging_slip_shipper` ON `tl_isotope_packaging_slip_shipper`.`id` = `tl_isotope_packaging_slip`.`shipper_id`
      WHERE `tl_isotope_packaging_slip`.`id` NOT IN (SELECT `pid` FROM `tl_isotope_packaging_slip_product_collection` WHERE `is_available` IN ('0', '-1'))
      AND `status` = '0' AND `check_availability` = '1' 
      ORDER BY `tl_isotope_packaging_slip`.`id` ASC 
      LIMIT 0, ?
    ";

    $objResult = $db->prepare($packagingSlipSql)->execute($maximumNumberOfPackagingSlipsToCheck);
    while($objResult->next()) {
      $isPaid = true;
      if ($objResult->handle_only_paid) {
        $packagingSlip = IsotopePackagingSlipModel::findByPk($objResult->id);
        foreach($packagingSlip->getOrders() as $order) {
          if (!$order->isPaid()) {
            $isPaid = false;
            break;
          }
        }
      }
      if ($isPaid) {
        $updateNotInStockSql = "UPDATE `tl_isotope_packaging_slip` SET `check_availability` = '0', `is_available` = '1', `availability_notes` = '' WHERE `id` = ?";
        $db->prepare($updateNotInStockSql)->execute($objResult->id);
      } else {
        $updateNotInStockSql = "UPDATE `tl_isotope_packaging_slip` SET `check_availability` = '0', `is_available` = '-1', `availability_notes` = ? WHERE `id` = ?";
        $notPaidNote = $GLOBALS['TL_LANG']['MSC']['PackageSlipOrderNotPaid'];
        $db->prepare($updateNotInStockSql)->execute($objResult->id, $notPaidNote);
      }
    }
  }

}