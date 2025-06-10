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

namespace Krabo\IsotopePackagingSlipBundle\Event;

use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;

class PackagingSlipOrderEvent {

  /**
   * @var \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel
   */
  private $packagingSlipModel;

  /**
   * @var \Isotope\Model\ProductCollection\Order
   */
  private $order;

  public $products = [];

  public function __construct(IsotopePackagingSlipModel $packagingSlipModel, Order $order, array $products=[]) {
    $this->packagingSlipModel = $packagingSlipModel;
    $this->order = $order;
    $this->products = $products;
  }

  /**
   * @return \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel
   */
  public function getPackagingSlip() {
    return $this->packagingSlipModel;
  }

  /**
   * @return \Isotope\Model\ProductCollection\Order
   */
  public function getOrder() {
    return $this->order;
  }

}