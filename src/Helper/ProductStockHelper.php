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

use Krabo\IsotopeStockBundle\Helper\ProductHelper;

class ProductStockHelper extends ProductHelper {

  /**
   * @var array
   */
  private static array $productAccountsPerPackagingSlipStatus = [];

  /**
   * @var array
   */
  private static array $productAccountTypesPerPackagingSlipStatus = [];

  /**
   * Returns the number of products for this account and with a packaging slip status.
   *
   * @param int $product_id
   * @param int $account_id
   * @param int $status_id
   *
   * @return int
   */
  public static function getProductCountPerAccountAndPackagingSlipStatus(int $product_id, int $account_id, int $status_id): int {
    self::loadStockInfoForProductOnlyPerPackagingSlipsStatus($product_id, $status_id);
    if (isset(self::$productAccountsPerPackagingSlipStatus[$product_id][$status_id][$account_id])) {
      return self::$productAccountsPerPackagingSlipStatus[$product_id][$status_id][$account_id]['balance'];
    }
    return 0;
  }

  /**
   * Load information about the stock for a certain product.
   *
   * @param int $product_id
   * @param int $status_id
   * @return void
   */
  private static function loadStockInfoForProductOnlyPerPackagingSlipsStatus(int $product_id, int $status_id) {
    if (isset(self::$productAccountsPerPackagingSlipStatus[$product_id]) && isset(self::$productAccountsPerPackagingSlipStatus[$product_id][$status_id])) {
      return;
    }
    \Contao\System::loadLanguageFile('tl_isotope_stock_account');
    $db = \Database::getInstance();
    $accountQueryResult = $db->prepare("SELECT * FROM `tl_isotope_stock_account` ORDER BY `type`, `title`")->execute();
    $accounts = [];
    $accountTypeBalance = [];
    while($accountQueryResult->next()) {
      $account_id = $accountQueryResult->id;
      $accounts[$account_id] = $accountQueryResult->row();
      $accounts[$account_id]['title'] = html_entity_decode($accounts[$account_id]['title']);
      $accounts[$account_id]['type_label'] = $GLOBALS['TL_LANG']['tl_isotope_stock_account']['type_options'][$accountQueryResult->type];
      $accounts[$account_id]['debit'] = 0;
      $accounts[$account_id]['credit'] = 0;
      $accounts[$account_id]['balance'] = 0;
      if ($accountQueryResult->type && !isset($accountTypeBalance[$accountQueryResult->type])) {
        $accountTypeBalance[$accountQueryResult->type]['balance'] = 0;
        $accountTypeBalance[$accountQueryResult->type]['label'] = $GLOBALS['TL_LANG']['tl_isotope_stock_account']['type_options'][$accountQueryResult->type];
      }
    }

    $productInfoQuery = "
      SELECT
        SUM(`tl_isotope_stock_booking_line`.`debit`) AS `debit`,
        SUM(`tl_isotope_stock_booking_line`.`credit`) AS `credit`,
        (SUM(`tl_isotope_stock_booking_line`.`debit`) - SUM(`tl_isotope_stock_booking_line`.`credit`)) AS `balance`,
         `tl_isotope_stock_booking_line`.`account`
      FROM `tl_isotope_stock_booking_line`
      LEFT JOIN `tl_isotope_stock_booking` ON `tl_isotope_stock_booking`.`id` = `tl_isotope_stock_booking_line`.`pid`
      LEFT JOIN `tl_isotope_stock_period` ON `tl_isotope_stock_period`.`id` = `tl_isotope_stock_booking`.`period_id` AND `tl_isotope_stock_period`.`active` = '1'
      LEFT JOIN `tl_isotope_packaging_slip` ON `tl_isotope_packaging_slip`.`id` = `tl_isotope_stock_booking`.`packaging_slip_id`
      WHERE `tl_isotope_stock_booking`.`product_id` = ? OR `tl_isotope_stock_booking`.`product_id` IS NULL
      AND `tl_isotope_packaging_slip`.`status` = ?
      GROUP BY `tl_isotope_stock_booking_line`.`account`
    ";
    $productInfoQueryResult = $db->prepare($productInfoQuery)->execute($product_id, $status_id);

    while($productInfoQueryResult->next()) {
      $accounts[$productInfoQueryResult->account]['debit'] = $productInfoQueryResult->debit;
      $accounts[$productInfoQueryResult->account]['credit'] = $productInfoQueryResult->credit;
      $accounts[$productInfoQueryResult->account]['balance'] = $productInfoQueryResult->balance;
      if (isset($accountTypeBalance[$accounts[$productInfoQueryResult->account]['type']])) {
        $accountTypeBalance[$accounts[$productInfoQueryResult->account]['type']]['balance'] += $productInfoQueryResult->balance;
      }
    }

    self::$productAccountsPerPackagingSlipStatus[$product_id][$status_id] = $accounts;
    self::$productAccountTypesPerPackagingSlipStatus[$product_id][$status_id] = $accountTypeBalance;
  }

}