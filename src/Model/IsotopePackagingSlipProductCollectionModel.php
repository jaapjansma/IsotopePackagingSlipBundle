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

use Contao\Model;
use Haste\Units\Mass\Unit;
use Haste\Units\Mass\WeightAggregate;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Helper\PackagingSlipCheckAvailability;
use Krabo\IsotopePackagingSlipBundle\Helper\StockBookingHelper;
use Krabo\IsotopeStockBundle\Model\BookingModel;
use Model\Registry;

/**
 * @property int $id
 * @property int $product_id
 * @property int $quantity
 * @property float $value
 * @property string $document_number
 */
class IsotopePackagingSlipProductCollectionModel extends Model {

  protected static $strTable = 'tl_isotope_packaging_slip_product_collection';

  /**
   * @var string
   */
  private $language;

  public function setLanguage($language) {
    $this->language = $language;
  }

  /**
   * Save orders into this packaging slip.
   *
   * @param IsotopePackagingSlipModel $packagingSlip
   * @param PackagingSlipProductModel[] $products
   * @param bool $resetAvailabilityStatus
   *
   * @return void
   */
  public static function saveProducts(IsotopePackagingSlipModel $packagingSlip, array $products, bool $resetAvailabilityStatus=true) {
    $db = \Database::getInstance();
    $objStmnt = $db->prepare("DELETE FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid` = ?");
    $objStmnt->execute($packagingSlip->id);
    StockBookingHelper::clearBookingForPackagingSlip($packagingSlip, BookingModel::SALES_TYPE);
    foreach($products as $product) {
      // Make sure the product gets inserted by clearing the registry
      Registry::getInstance()->unregister($product);
      $newProduct = clone $product;
      $product->pid = $packagingSlip->id;
      $product->tstamp = time();

      StockBookingHelper::createSalesBookingFromPackagingSlipAndProduct($packagingSlip, $product);
      $product->save();
    }
    if ($resetAvailabilityStatus) {
      PackagingSlipCheckAvailability::resetAvailabilityStatus([$packagingSlip->id]);
    }
  }

  /**
   * Load the products combined per product id per packaging slip.
   *
   * @param IsotopePackagingSlipModel $packagingSlip
   * @return array
   */
  public static function getCombinedProductsByPackagingSlip(IsotopePackagingSlipModel $packagingSlip): array {
    \Contao\System::loadLanguageFile('tl_isotope_packaging_slip');
    \Contao\System::loadLanguageFile('default');

    $strTable = static::$strTable;
    $db = \Database::getInstance();
    $objStmnt = $db->prepare("SELECT * FROM `" . $strTable . "` WHERE `pid` = ? ORDER BY `product_id`");
    $objResult = $objStmnt->execute($packagingSlip->id);

    $arrProducts = array();

    while ($objResult->next())
    {
      $objProduct = new IsotopePackagingSlipProductCollectionModel();
      $objProduct->setRow($objResult->row());
      if ($objResult->document_number) {
        $order = Order::findOneBy('document_number', $objResult->document_number);
        $objProduct->setLanguage($order->language);
        \Contao\System::loadLanguageFile('tl_isotope_packaging_slip', $order->language);
        \Contao\System::loadLanguageFile('default', $order->language);
      }
      if (isset($arrProducts[$objProduct->product_id])) {
        $arrProducts[$objProduct->product_id]->quantity += $objProduct->quantity;
        $arrProducts[$objProduct->product_id]->value += $objProduct->value;
      } else {
        $arrProducts[$objProduct->product_id] = $objProduct;
      }
    }
    return $arrProducts;
  }

  /**
   * @return float
   */
  public function getWeightInKg(): float {
    $objProduct = $this->getProduct();
    if ($objProduct instanceof WeightAggregate && $objProduct->getWeight()) {
      $productWeight = $objProduct->getWeight()->getWeightValue();
      $productWeight = $this->quantity * $productWeight;
      $productWeightUnit = $objProduct->getWeight()->getWeightUnit();
      return (float) Unit::convert($productWeight, $productWeightUnit, 'kg');
    }
    return 0.00;
  }

  /**
   * Returns Product
   *
   * @return \Isotope\Model\Product
   */
  public function getProduct() {
    $oldLanguage = $GLOBALS['TL_LANGUAGE'];
    if ($this->language) {
      $GLOBALS['TL_LANGUAGE'] = $this->language;
    }

    $product = Product::findByPk($this->product_id);
    $GLOBALS['TL_LANGUAGE'] = $oldLanguage;
    return $product;
  }

}