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

namespace Krabo\IsotopePackagingSlipBundle\Model;

use Contao\Database;
use Contao\Model;
use Database\Result;
use Isotope\Model\Product;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Helper\AddressHelper;
use Krabo\IsotopePackagingSlipBundle\Helper\StockBookingHelper;

class PackagingSlipModel extends Model {

  protected static $strTable = 'tl_isotope_packaging_slip';

  private static $newOrders = [];

  /**
   * Construct the model
   *
   * @param Result $objResult
   */
  public function __construct(Result $objResult = null)
  {
    parent::__construct($objResult);

    if (!\is_array($GLOBALS['ISO_ADR'])) {
      \System::loadLanguageFile('addresses');
    }
  }

  /**
   * Generate the next higher Document Number based on existing records
   *
   * @param string $strPrefix
   * @param int    $intDigits
   *
   * @return string
   * @throws \Exception
   */
  public function generateDocumentNumber($strPrefix, $intDigits)
  {
    $db = \Database::getInstance();
    if ($this->arrData['document_number'] != '') {
      return $this->arrData['document_number'];
    }

    try {
      if ($this->arrData['document_number'] == '') {
        $strPrefix = \Controller::replaceInsertTags($strPrefix, false);
        $intPrefix = utf8_strlen($strPrefix);

        // Lock tables so no other order can get the same ID
        $db->lockTables(array(static::$strTable => 'WRITE'));

        $prefixCondition = ($strPrefix != '' ? " AND document_number LIKE '$strPrefix%'" : '');

        // Retrieve the highest available order ID
        $objMax = $db
          ->prepare("SELECT `document_number` FROM `".static::$strTable."` WHERE 1 $prefixCondition ORDER BY CAST(" . ($strPrefix != '' ? 'SUBSTRING(document_number, ' . ($intPrefix + 1) . ')' : 'document_number') . ' AS UNSIGNED) DESC')
          ->limit(1)
          ->execute();

        $intMax = (int) substr($objMax->document_number, $intPrefix);

        $this->arrData['document_number'] = $strPrefix . str_pad($intMax + 1, $intDigits, '0', STR_PAD_LEFT);
      }
      $db->prepare('UPDATE `'.static::$strTable.'` SET document_number=? WHERE id=?')->execute($this->arrData['document_number'], $this->id);
      $db->unlockTables();
    } catch (\Exception $e) {
      // Make sure tables are always unlocked
      $db->unlockTables();

      throw $e;
    }

    return $this->arrData['document_number'];
  }

  /**
   * Save orders into this packaging slip.
   *
   * @param int $id
   * @param $order_ids
   *
   * @return void
   */
  public static function saveOrders(int $id, $order_ids) {
    if (!\is_array($order_ids) || empty($order_ids)) {
      \Database::getInstance()->query("DELETE FROM `tl_isotope_packaging_slip_order_collection` WHERE `pid`={$id}");
    } else {
      $arrOld = \Database::getInstance()->execute("SELECT `order_id` FROM `tl_isotope_packaging_slip_order_collection` WHERE `pid`={$id}")->fetchEach('order_id');

      $arrInsert = array_diff($order_ids, $arrOld);
      $arrDelete = array_diff($arrOld, $order_ids);

      if (!empty($arrDelete)) {
        \Database::getInstance()->query("DELETE FROM `tl_isotope_packaging_slip_order_collection` WHERE `pid`={$id} AND `order_id` IN (" . implode(',', $arrDelete) . ")");
      }

      if (!empty($arrInsert)) {
        $time = time();
        \Database::getInstance()->query("INSERT INTO `tl_isotope_packaging_slip_order_collection` (`pid`,`tstamp`,`order_id`) VALUES ({$id}, $time, " . implode("), ({$id}, $time, ", $arrInsert) . ")");
        self::$newOrders[$id] = $arrInsert;
      }
    }
  }

  /**
   * Save orders into this packaging slip.
   *
   * @param int $id
   * @param $order_ids
   *
   * @return void
   */
  public static function addOrder(int $packaging_slip_id, $order_id) {
      $time = time();
      \Database::getInstance()->query("INSERT INTO `tl_isotope_packaging_slip_order_collection` (`pid`,`tstamp`,`order_id`) VALUES ($packaging_slip_id, $time, $order_id)");
      self::$newOrders[$packaging_slip_id] = [$order_id];
  }

  /**
   * Save orders into this packaging slip.
   *
   * @param int $packaging_slip_id
   * @param array $products
   *   - key is the product id
   *   - value the quantity
   *
   * @return void
   */
  public static function saveProducts(int $packaging_slip_id, $products) {
    if (isset(self::$newOrders[$packaging_slip_id]) && count(self::$newOrders[$packaging_slip_id])) {
      $strOrderIds = implode(",", self::$newOrders[$packaging_slip_id]);
      $newOrderProduct = \Database::getInstance()->execute("SELECT `product_id`, `quantity` FROM `tl_iso_product_collection_item` WHERE `pid` IN ({$strOrderIds})");
      while ($newOrderProduct->next()) {
        if (!isset($products[$newOrderProduct->product_id])) {
          $products[$newOrderProduct->product_id] = 0;
        }
        $products[$newOrderProduct->product_id] = $products[$newOrderProduct->product_id] + $newOrderProduct->quantity;
      }
    }
    \Database::getInstance()->query("DELETE FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid`={$packaging_slip_id}");
    if (!empty($products)) {
      $time = time();
      $insertQuery = "INSERT INTO `tl_isotope_packaging_slip_product_collection` (`pid`,`tstamp`,`product_id`, `quantity`) VALUES ";
      $insertRows = [];
      foreach ($products as $product_id => $quantity) {
        $insertRows[] = " ({$packaging_slip_id}, {$time}, {$product_id}, {$quantity})";
      }
      $insertQuery .= implode(", ", $insertRows);
      \Database::getInstance()->query($insertQuery);
    }
  }

  /**
   * Checks whether an order already exists on a packaging slip.
   *
   * @param $order_id
   *
   * @return bool
   */
  public static function doesOrderExists($order_id): bool {
    $db = Database::getInstance();
    return (bool) $db->prepare("SELECT COUNT(*) FROM `tl_isotope_packaging_slip_order_collection` WHERE `order_id` = ?")->execute($order_id)->first()->fetchField(0);
  }

  /**
   * Return formatted address (hCard)
   *
   * @return string
   *
   * @throws \Exception on error parsing simple tokens
   */
  public function generateAddress()
  {
    // We need a country to format the address, use default country if none is available
    $strCountry = $this->country ?: Isotope::getConfig()->country;
    // Use generic format if no country specific format is available
    $strFormat = $GLOBALS['ISO_ADR'][$strCountry] ?: $GLOBALS['ISO_ADR']['generic'];
    $arrTokens  = AddressHelper::getAddressTokens($this);
    return \StringUtil::parseSimpleTokens($strFormat, $arrTokens);
  }

  /**
   * Get the list of products in this packaging slip.
   *
   * @return array
   */
  public function getProducts() {
    $result = \Database::getInstance()->prepare("SELECT `product_id`, `quantity` FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid`= ?")->execute($this->id);
    $return = [];
    while($result->next()) {
      $return[$result->product_id] = [];
      $return[$result->product_id]['quantity'] = $result->quantity;
      $return[$result->product_id]['product'] = Product::findByPk($result->product_id);
    }
    return $return;
  }

  /**
   * Get the list of orders in this packaging slip.
   *
   * @return array
   */
  public function getOrders() {
    $result = \Database::getInstance()->prepare("SELECT `order_id` FROM `tl_isotope_packaging_slip_order_collection` WHERE `pid`= ?")->execute($this->id);
    $return = [];
    while($result->next()) {
      $return[$result->order_id] = Order::findByPk($result->order_id);
    }
    return $return;
  }

  /**
   * @return string
   */
  public function getOrderDocumentNumbers() {
    $documenntNumbers = [];
    foreach($this->getOrders() as $order) {
      $documenntNumbers[] = $order->document_number;
    }
    return implode(", ", $documenntNumbers);
  }

  /**
   * Update the stock booking.
   *
   * @return void
   */
  public function updateStock() {
    $result = \Database::getInstance()->prepare("SELECT `product_id`, `quantity` FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid`= ?")->execute($this->id);
    while($result->next()) {
      $product = Product::findByPk($result->product_id);
      if ($product) {
        StockBookingHelper::createBookingFromPackagingSlipAndProduct($this, $result->quantity, $product);
      }
    }
  }


}