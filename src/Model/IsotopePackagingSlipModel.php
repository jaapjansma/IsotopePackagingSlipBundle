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
use Contao\Date;
use Contao\MemberModel;
use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Database\Result;
use Haste\Util\Format;
use Isotope\Model\Address;
use Isotope\Model\Document;
use Isotope\Model\Product;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\Shipping;
use Isotope\Template;
use Krabo\IsotopePackagingSlipBundle\Event\Events;
use Krabo\IsotopePackagingSlipBundle\Event\GenerateAddressEvent;
use Krabo\IsotopePackagingSlipBundle\Event\StatusChangedEvent;
use Krabo\IsotopePackagingSlipBundle\Helper\AddressHelper;
use Krabo\IsotopePackagingSlipBundle\Helper\StockBookingHelper;
use Krabo\IsotopePackagingSlipBundle\Helper\TemplateHelper;
use Krabo\IsotopeStockBundle\Model\BookingModel;
use Money\Currencies;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NotificationCenter\Model\Notification;
use NotificationCenter\Model\QueuedMessage;


class IsotopePackagingSlipModel extends Model {

  protected static $strTable = 'tl_isotope_packaging_slip';

  private $oldStatus = null;

  const STATUS_OPEN = 0;
  const STATUS_PREPARE_FOR_SHIPPING = 1;
  const STATUS_SHIPPED = 2;
  const STATUS_READY_FOR_PICKUP = 3;
  const STATUS_DELIVERED = 4;
  const STATUS_PICKED_UP = 5;
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
   * @param \Isotope\Model\ProductCollection\Order $order
   *
   * @return Collection|null The model collection or null if there are no records
   */
  public static function findPackagingSlipsByOrder(Order $order) {
    $db = Database::getInstance();
    $pids = $db
      ->prepare("SELECT `pid` FROM `tl_isotope_packaging_slip_product_collection` WHERE `document_number` = ? GROUP BY `pid`")
      ->execute($order->document_number)
      ->fetchEach('pid');
    return static::findMultipleByIds($pids);
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
   * @return string
   */
  public function getAmountToPaid(): string {
    $amountToPaid = null;
    foreach($this->getOrders() as $order) {
      if (!$order->isPaid()) {
        $orderTotal = new Money((int) ($order->total*100), new \Money\Currency($order->currency));
        if ($amountToPaid===null) {
          $amountToPaid = $orderTotal;
        } else {
          $amountToPaid->add($orderTotal);
        }
      }
    }
    if ($amountToPaid) {
      $currencies = new Currencies\ISOCurrencies();
      $numberFormatter = new \NumberFormatter('', \NumberFormatter::CURRENCY);
      $moneyFormatter = new IntlMoneyFormatter($numberFormatter, $currencies);
      return $moneyFormatter->format($amountToPaid);
    }
    return '';
  }

  /**
   * @return string
   */
  public function getStatusLabel() {
    return $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['status_options'][$this->status];
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
    if ($products) {
      foreach ($products as $product) {
        $weight += $product->getWeightInKg();
      }
    }
    return (int) round($weight);
  }

  /**
   * Get the list of orders in this packaging slip.
   *
   * @return \Isotope\Model\ProductCollection\Order[]
   */
  public function getOrders() {
    $result = \Database::getInstance()->prepare("SELECT `document_number` FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid`= ? AND `document_number` != '' GROUP BY `document_number`")->execute($this->id);
    $return = [];
    while($result->next()) {
      $order = Order::findOneBY('document_number', $result->document_number);
      if ($order) {
        $return[$order->id] = $order;
      }
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
    if ($this->status !== null && $this->oldStatus !== null && $this->status != $this->oldStatus) {
      $this->triggerStatusChangedEvent($this->oldStatus, $this->status);
    }
    parent::postSave($intType);
  }

  /**
   * Trigger the status changed Event.
   *
   * @param int $oldStatus
   * @param int $newStatus
   * @param bool $isDelayed
   *
   * @return void
   */
  public function triggerStatusChangedEvent(int $oldStatus, int $newStatus, $isDelayed=false) {
    if ($newStatus == self::STATUS_PREPARE_FOR_SHIPPING && $oldStatus != $newStatus && !$isDelayed) {
      $this->updateDeliveryStock();
    }

    $delay_status_change_event = false;
    if ($this->shipping_date) {
      $today = new \DateTime();
      $shipping_date = new \DateTime();
      $shipping_date->setTimestamp($this->shipping_date);
      if ($shipping_date > $today) {
        $delay_status_change_event = TRUE;
      }
    }

    if (!$delay_status_change_event) {
      $objNotificationCollection = \NotificationCenter\Model\Notification::findByType('isotope_packaging_slip_status_' . $newStatus);
      $arrTokens = $this->getNotificationTokens();
      $arrTokens['old_status_id'] = $oldStatus;
      $arrTokens['old_status'] = $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['status_options'][$oldStatus];
      if (NULL !== $objNotificationCollection) {
        $objNotificationCollection->reset();
        while ($objNotificationCollection->next()) {
          $objNotification = $objNotificationCollection->current();
          $objNotification->send($arrTokens);
        }
      }

      $event = new StatusChangedEvent($this, $oldStatus, $newStatus);
      System::getContainer()
        ->get('event_dispatcher')
        ->dispatch($event, Events::STATUS_CHANGED_EVENT);

      $r = \Contao\Database::getInstance()
        ->prepare("UPDATE `tl_isotope_packaging_slip` SET `fire_status_changed_event_on_shipping_date` = '0', `old_status` = NULL, `new_status` = NULL WHERE `id` = ?")
        ->execute($this->id);
    } else {
      \Contao\Database::getInstance()
        ->prepare("UPDATE `tl_isotope_packaging_slip` SET `fire_status_changed_event_on_shipping_date` = '1', `old_status` = ?, `new_status` = ? WHERE `id` = ?")
        ->execute($oldStatus, $newStatus, $this->id);
    }
  }

  /**
   * Retrieve the array of notification data for parsing simple tokens
   *
   * @return array
   */
  public function getNotificationTokens()
  {
    $objConfig = $this->getRelated('config_id') ?: Isotope::getConfig();
    Isotope::setConfig($objConfig);

    $arrTokens                    = $this->row();
    $arrTokens['status_id']       = $this->status;
    $arrTokens['status']          = $this->getStatusLabel();
    $arrTokens['recipient_email'] = $this->getEmailRecipient();
    $arrTokens['order_id']        = $this->id;
    $arrTokens['document']        = TemplateHelper::generatePackagingSlipHTML($this, 'packaging_slip_document_compact');
    // Add shipping method info
    $objShipping = Shipping::findByPk($this->shipping_id);
    $arrTokens['shipping_id']        = $objShipping->getId();
    $arrTokens['shipping_label']     = $objShipping->getLabel();
    $arrTokens['shipping_note']      = $objShipping->getNote();
    foreach ($objShipping->row() as $k => $v) {
      $arrTokens['shipping_method_' . $k] = $v;
    }
    foreach ($this->row() as $k => $v) {
      $arrTokens['packaging_slip_' . $k] = $v;
    }
    if (!empty($arrTokens['shipping_date'])) {
      $shippingDate = new \DateTime();
      $shippingDate->setTimestamp($arrTokens['shipping_date']);
      $arrTokens['shipping_date'] = $shippingDate->format(Date::getNumericDateFormat());
    }
    return $arrTokens;
  }

  /**
   * Return customer email address for the collection
   *
   * @return string
   */
  public function getEmailRecipient()
  {
    $strName  = $this->firstname . ' ' . $this->lastname;
    $strEmail = $this->email;
    if (trim($strName) != '') {
      // Romanize friendly name to prevent email issues
      $strName = html_entity_decode($strName, ENT_QUOTES, $GLOBALS['TL_CONFIG']['characterSet']);
      $strName = strip_insert_tags($strName);
      $strName = utf8_romanize($strName);
      $strName = preg_replace('/[^A-Za-z0-9\.!#$%&\'*+-\/=?^_ `{\|}~]+/i', '_', $strName);
      $strEmail = sprintf('"%s" <%s>', $strName, $strEmail);
    }
    return $strEmail;
  }


}