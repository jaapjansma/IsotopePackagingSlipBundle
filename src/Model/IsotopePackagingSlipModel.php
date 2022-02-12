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
use Contao\System;
use Database\Result;
use Isotope\Model\Product;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Event\Events;
use Krabo\IsotopePackagingSlipBundle\Event\GenerateAddressEvent;
use Krabo\IsotopePackagingSlipBundle\Event\StatusChangedEvent;
use Krabo\IsotopePackagingSlipBundle\Helper\AddressHelper;
use Krabo\IsotopePackagingSlipBundle\Helper\StockBookingHelper;
use Krabo\IsotopeStockBundle\Model\BookingModel;

class IsotopePackagingSlipModel extends Model {

  protected static $strTable = 'tl_isotope_packaging_slip';

  private $oldStatus = null;

  const STATUS_OPEN = 0;
  const STATUS_PREPARE_FOR_SHIPPING = 1;
  const STATUS_SHIPPED = 2;
  const STATUS_DELIVERED = 3;
  const STATUS_ONHOLD = -1;

  private $products;

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
   * Checks whether an order already exists on a packaging slip.
   *
   * @param Order $order
   *
   * @return bool
   */
  public static function doesOrderExists(Order $order): bool {
    $db = Database::getInstance();
    return (bool) $db->prepare("SELECT COUNT(*) FROM `tl_isotope_packaging_slip_product_collection` WHERE `document_number` = ?")->execute($order->document_number)->first()->fetchField(0);
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
    $strAddress = \StringUtil::parseSimpleTokens($strFormat, $arrTokens);
    $event = new GenerateAddressEvent($this, $strAddress);
    System::getContainer()->get('event_dispatcher')->dispatch($event, Events::GENERATE_ADDRESS);
    return $event->getGeneratedAddress();
  }

  /**
   * @return array
   */
  public function getProductsCombinedByProductId() {
    return IsotopePackagingSlipProductCollectionModel::getCombinedProductsByPackagingSlip($this);
  }

  /**
   * Returns the total weight in KG.
   *
   * @return int
   */
  public function getTotalWeight(): int {
    $weight = 0.00;
    $products = IsotopePackagingSlipProductCollectionModel::findBy('pid', $this->id);
    foreach($products as $product) {
      $weight += $product->getWeightInKg();
    }
    return (int) round($weight);
  }

  /**
   * Get the list of orders in this packaging slip.
   *
   * @return array
   */
  public function getOrders() {
    $result = \Database::getInstance()->prepare("SELECT `document_number` FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid`= ? AND `document_number` != '' GROUP BY `document_number`")->execute($this->id);
    $return = [];
    while($result->next()) {
      $order = Order::findOneBY('document_number', $result->document_number);
      $return[$order->id] = $order;
    }
    return $return;
  }

  /**
   * @return string
   */
  public function getOrderDocumentNumbers() {
    $documentNumbers = [];
    foreach($this->getOrders() as $order) {
      if ($order->document_number && !in_array($order->document_number, $documentNumbers))
        $documentNumbers[] = $order->document_number;
    }
    return implode(", ", $documentNumbers);
  }

  /**
   * Update the stock booking.
   *
   * @return void
   */
  public function updateDeliveryStock() {
    $result = \Database::getInstance()->prepare("SELECT `product_id`, `quantity`, `document_number` FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid`= ?")->execute($this->id);
    while($result->next()) {
      $product = Product::findByPk($result->product_id);
      if ($product) {
        StockBookingHelper::createDeliveryBookingFromPackagingSlipAndProduct($this, $result->quantity, $product, $result->document_number);
      }
    }
  }

  /**
   * Modify the current row before it is stored in the database
   *
   * @param array $arrSet The data array
   *
   * @return array The modified data array
   */
  protected function preSave(array $arrSet)
  {
    $this->oldStatus = null;
    if ($this->id) {
      $objDatabase = Database::getInstance();
      $objResult = $objDatabase->prepare("SELECT `status` FROM `" . static::$strTable . "` WHERE `id` = ?")->execute([$this->id]);
      if ($objResult->next()) {
        $this->oldStatus = $objResult->status;
      }
    } elseif ($this->status) {
      $this->oldStatus = $this->status;
    }
    return parent::preSave($arrSet);
  }

  /**
   * Modify the current row after it has been stored in the database
   *
   * @param integer $intType The query type (Model::INSERT or Model::UPDATE)
   */
  protected function postSave($intType)
  {
    if ($this->status && $this->oldStatus && $this->status != $this->oldStatus) {
      $this->triggerStatusChangedEvent($this->oldStatus, $this->status);
    }
    parent::postSave($intType);
  }

  /**
   * Trigger the status changed Event.
   *
   * @param int $oldStatus
   * @param int $newStatus
   *
   * @return void
   */
  public function triggerStatusChangedEvent(int $oldStatus, int $newStatus) {
    if ($newStatus == self::STATUS_PREPARE_FOR_SHIPPING && $oldStatus != $newStatus) {
      $this->updateDeliveryStock();
    }
    $event = new StatusChangedEvent($this, $oldStatus, $newStatus);
    System::getContainer()->get('event_dispatcher')->dispatch($event, Events::STATUS_CHANGED_EVENT);
  }


}