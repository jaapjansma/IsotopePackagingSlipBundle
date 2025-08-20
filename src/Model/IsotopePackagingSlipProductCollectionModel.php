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
 * @property string $options
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
   * @param IsotopePackagingSlipProductCollectionModel[] $products
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
      $product->pid = $packagingSlip->id;
      $product->tstamp = time();
      $weight = static::getWeightForIsoProduct($product->product_id);
      $product->weight = $weight;

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
    $objStmnt = $db->prepare("SELECT * FROM `" . $strTable . "` WHERE `pid` = ? ORDER BY `weight`");
    $objResult = $objStmnt->execute($packagingSlip->id);

    $arrWeightedProducts = array();
    $arrProducts = array();

    while ($objResult->next())
    {
      $objProduct = new IsotopePackagingSlipProductCollectionModel();
      $objProduct->setRow($objResult->row());
      $key = $objResult->weight . '_' . $objProduct->product_id . ':' . md5($objProduct->options);
      if ($objResult->document_number) {
        $order = Order::findOneBy('document_number', $objResult->document_number);
        $objProduct->setLanguage($order->language);
      }
      if (isset($arrWeightedProducts[$objResult->weight][$key])) {
        $arrWeightedProducts[$objResult->weight][$key]->quantity += $objProduct->quantity;
        $arrWeightedProducts[$objResult->weight][$key]->value += $objProduct->value;
      } else {
        $arrWeightedProducts[$objResult->weight][$key] = $objProduct;
      }
    }
    foreach ($arrWeightedProducts as $weight => $arrWeightedProduct) {
      foreach ($arrWeightedProduct as $key => $product) {
        $arrProducts[$key] = $product;
      }
    }
    return $arrProducts;
  }

  public static function getWeightForIsoProduct(int $isoProductId): ?int {
    $weight = 0;
    $isoProduct = null;
    if ($isoProductId > 0) {
      $isoProduct = Product::findByPk($isoProductId);
    }
    if ($isoProduct) {
      if (strlen($isoProduct->isotope_packaging_slip_position) && is_numeric($isoProduct->isotope_packaging_slip_position)) {
        $weight = $isoProduct->isotope_packaging_slip_position;
      } else {
        $productType = $isoProduct->getType();
        if ($productType && strlen($productType->isotope_packaging_slip_position) && is_numeric($productType->isotope_packaging_slip_position)) {
          $weight = $productType->isotope_packaging_slip_position;
        }
      }
    }
    return $weight;
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
    $product = Product::findByPk($this->product_id);
    Registry::getInstance()->unregister($product);
    $product = Product::findByPk($this->product_id);
    return $product;
  }

}