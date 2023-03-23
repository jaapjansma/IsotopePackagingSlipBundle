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
use Krabo\IsotopePackagingSlipBundle\Event\CheckAvailabilityEvent;
use Krabo\IsotopePackagingSlipBundle\Event\Events;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopeStockBundle\Helper\ProductHelper;
use Krabo\IsotopeStockBundle\Model\AccountModel;
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
    $db = System::importStatic('Database');
    $today = new DateTime();
    $today->setTime(23,59);
    $productSql = "
        SELECT `packaging_slip_product`.`product_id`, `packaging_slip`.`credit_account` 
        FROM `tl_isotope_packaging_slip_product_collection` `packaging_slip_product`
        INNER JOIN `tl_isotope_packaging_slip` `packaging_slip` ON `packaging_slip_product`.`pid` = `packaging_slip`.`id`
        WHERE  `packaging_slip`.`status` = '0' AND `packaging_slip`.`check_availability` = '1' 
        AND (`packaging_slip`.`scheduled_picking_date` = '' OR `packaging_slip`.`scheduled_picking_date` <= ?)
        AND `packaging_slip_product`.`is_available` = '0'
        GROUP BY `product_id`, `packaging_slip`.`credit_account`
        ORDER BY `product_id` ASC, `credit_account` ASC
        LIMIT 0, ?";
    $productSumSql = "
        SELECT `packaging_slip_product`.`product_id`, SUM(`packaging_slip_product`.`quantity`) AS `quantity`, `packaging_slip`.`credit_account` 
        FROM `tl_isotope_packaging_slip_product_collection` `packaging_slip_product`
        INNER JOIN `tl_isotope_packaging_slip` `packaging_slip` ON `packaging_slip_product`.`pid` = `packaging_slip`.`id`
        WHERE  `packaging_slip`.`status` = '0' AND `packaging_slip`.`check_availability` = '1' 
        AND (`packaging_slip`.`scheduled_picking_date` = '' OR `packaging_slip`.`scheduled_picking_date` <= ?)
        AND `packaging_slip_product`.`product_id` = ? 
        AND `packaging_slip`.`credit_account` = ?
        GROUP BY `product_id`, `packaging_slip`.`credit_account`
        ORDER BY `product_id` ASC, `quantity` ASC
    ";
    $updateProductSql = "
        UPDATE `tl_isotope_packaging_slip_product_collection` `packaging_slip_product`
        INNER JOIN `tl_isotope_packaging_slip` `packaging_slip` ON `packaging_slip_product`.`pid` = `packaging_slip`.`id`
        SET `packaging_slip_product`.`is_available` = ?
        WHERE `packaging_slip_product`.`product_id` = ? AND `packaging_slip`.`credit_account` = ? 
        AND `packaging_slip`.`status` = '0' AND `packaging_slip`.`check_availability` = '1' AND (`packaging_slip`.`scheduled_picking_date` = '' OR `packaging_slip`.`scheduled_picking_date` <= ?)";
    $objResult = $db->prepare($productSql)->execute($today->getTimestamp(), $maximumNumberOfProductsToCheck);
    while ($objResult->next()) {
      $objQuantity = $db->prepare($productSumSql)->execute($today->getTimestamp(), $objResult->product_id, $objResult->credit_account);
      $stock = ProductHelper::getProductCountPerAccount($objResult->product_id, $objResult->credit_account);
      if ($stock >= $objQuantity->quantity) {
        // Product is available.
        $db->prepare($updateProductSql)->execute('1', $objResult->product_id, $objResult->credit_account, $today->getTimestamp());
      } else {
        // Product is not available.
        $db->prepare($updateProductSql)->execute('-1', $objResult->product_id, $objResult->credit_account, $today->getTimestamp());
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

    $packagingSlipSql = "
      SELECT `tl_isotope_packaging_slip`.`id`, `tl_isotope_packaging_slip_shipper`.`handle_only_paid`
      FROM `tl_isotope_packaging_slip`
      LEFT JOIN `tl_isotope_packaging_slip_shipper` ON `tl_isotope_packaging_slip_shipper`.`id` = `tl_isotope_packaging_slip`.`shipper_id`
      WHERE `tl_isotope_packaging_slip`.`id` NOT IN (SELECT `pid` FROM `tl_isotope_packaging_slip_product_collection` WHERE `is_available` IN ('0', '-1'))
      AND `status` = '0' AND `check_availability` = '1' 
      AND (`scheduled_picking_date` IS NULL OR DATE(FROM_UNIXTIME(`scheduled_picking_date`)) <= CURRENT_DATE())
      ORDER BY `tl_isotope_packaging_slip`.`id` ASC 
      LIMIT 0, ?
    ";

    $objResult = $db->prepare($packagingSlipSql)->execute($maximumNumberOfPackagingSlipsToCheck);
    while($objResult->next()) {
      $event = new CheckAvailabilityEvent();
      $event->packagingSlipId = $objResult->id;
      $event->isAvailable = '1';
      $isPaid = true;
      if ($objResult->handle_only_paid) {
        $packagingSlip = IsotopePackagingSlipModel::findByPk($objResult->id);
        foreach($packagingSlip->getOrders() as $order) {
          if (!$order->isPaid()) {
            $event->isAvailable = '-1';
            $event->notes = $GLOBALS['TL_LANG']['MSC']['PackageSlipOrderNotPaid'];
            break;
          }
        }
      }
      System::getContainer()->get('event_dispatcher')->dispatch($event, Events::CHECK_AVAILABILITY);
      $updateSql = "UPDATE `tl_isotope_packaging_slip` SET `check_availability` = '0', `is_available` = ?, `availability_notes` = ? WHERE `id` = ?";
      $db->prepare($updateSql)->execute($event->isAvailable, $event->notes, $objResult->id);
    }
  }

}