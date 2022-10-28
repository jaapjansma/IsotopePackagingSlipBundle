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

namespace Krabo\IsotopePackagingSlipBundle\EventListener;

use Isotope\Model\OrderStatus;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Event\Events;
use Krabo\IsotopePackagingSlipBundle\Event\StatusChangedEvent;
use Krabo\IsotopePackagingSlipBundle\Helper\IsotopeHelper;
use Krabo\IsotopePackagingSlipBundle\Helper\StockBookingHelper;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopeStockBundle\Model\BookingModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PackagingSlipStatusChangedListener implements EventSubscriberInterface {

  private $isotopeOrderCancelStatus;

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * The array keys are event names and the value can be:
   *
   *  * The method name to call (priority defaults to 0)
   *  * An array composed of the method name to call and the priority
   *  * An array of arrays composed of the method names to call and respective
   *    priorities, or 0 if unset
   *
   * For instance:
   *
   *  * ['eventName' => 'methodName']
   *  * ['eventName' => ['methodName', $priority]]
   *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
   *
   * The code must not depend on runtime state as it will only be called at
   * compile time. All logic depending on runtime state must be put into the
   * individual methods handling the events.
   *
   * @return array<string, mixed> The event names to listen to
   */
  public static function getSubscribedEvents() {
    return [
      Events::STATUS_CHANGED_EVENT => 'onPackagingSlipStatusChanged',
    ];
  }

  public function onPackagingSlipStatusChanged(StatusChangedEvent $event) {
    $newStatus = $event->getNewStatus();
    $oldStatus = $event->getOldStatus();
    $isDelayed = $event->isDelayed();
    if ($newStatus == IsotopePackagingSlipModel::STATUS_PREPARE_FOR_SHIPPING && $oldStatus != $newStatus && !$isDelayed) {
      $this->updateDeliveryStock($event->getPackagingSlip());
    } elseif ($newStatus == IsotopePackagingSlipModel::STATUS_CANCELLED && $oldStatus != $newStatus) {
      $packagingSlip = $event->getPackagingSlip();
      $this->cancelDeliveryStock($packagingSlip);
      $this->cancelSalesStock($packagingSlip);
      $this->cancelRelatedOrders($event->getPackagingSlip());
    }
  }

  /**
   * Update the stock booking.
   *
   * @param IsotopePackagingSlipModel $packagingSlipModel
   * @return void
   */
  private function updateDeliveryStock(IsotopePackagingSlipModel $packagingSlipModel) {
    $result = \Database::getInstance()->prepare("SELECT `product_id`, `quantity`, `document_number` FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid`= ?")->execute($packagingSlipModel->id);
    while($result->next()) {
      $product = Product::findByPk($result->product_id);
      if ($product) {
        StockBookingHelper::createDeliveryBookingFromPackagingSlipAndProduct($packagingSlipModel, $result->quantity, $product, $result->document_number);
      }
    }
  }

  /**
   * Update the stock booking.
   *
   * @param IsotopePackagingSlipModel $packagingSlipModel
   * @return void
   */
  private function cancelDeliveryStock(IsotopePackagingSlipModel $packagingSlipModel) {
    StockBookingHelper::clearBookingForPackagingSlip($packagingSlipModel,BookingModel::DELIVERY_TYPE);
  }

  /**
   * Update the stock booking.
   *
   * @param IsotopePackagingSlipModel $packagingSlipModel
   * @return void
   */
  private function cancelSalesStock(IsotopePackagingSlipModel $packagingSlipModel) {
    StockBookingHelper::clearBookingForPackagingSlip($packagingSlipModel,BookingModel::SALES_TYPE);
  }

  /**
   * @param \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel $packagingSlipModel
   *
   * @return void
   */
  private function cancelRelatedOrders(IsotopePackagingSlipModel $packagingSlipModel) {
    $cancelOrderStatus = $this->getIsotopeCancelOrderStatus();
    if ($cancelOrderStatus) {
      foreach ($packagingSlipModel->getOrders() as $objOrder) {
        $objOrder->updateOrderStatus($cancelOrderStatus->id);
      }
    }
  }

  /**
   * @return \Contao\Model|\Contao\Model[]|\Contao\Model\Collection|\Isotope\Model\OrderStatus|null
   */
  private function getIsotopeCancelOrderStatus() {
    if (!$this->isotopeOrderCancelStatus) {
      $this->isotopeOrderCancelStatus = OrderStatus::findOneBy('isotope_packagingslip_cancel_status', '1');
    }
    return $this->isotopeOrderCancelStatus;
  }

}