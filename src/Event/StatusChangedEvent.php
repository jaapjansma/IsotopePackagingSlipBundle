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

use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;

class StatusChangedEvent {

  /**
   * @var \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel
   */
  private $packagingSlipModel;

  /**
   * @var int
   */
  private $oldStatus;

  /**
   * @var int
   */
  private $newStatus;

  /**
   * @var bool
   */
  private $isDelayed;

  public function __construct(IsotopePackagingSlipModel $packagingSlipModel, int $oldStatus, int $newStatus, bool $isDelayed) {
    $this->packagingSlipModel = $packagingSlipModel;
    $this->oldStatus = $oldStatus;
    $this->newStatus = $newStatus;
    $this->isDelayed = $isDelayed;
  }

  /**
   * @return \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel
   */
  public function getPackagingSlip() {
    return $this->packagingSlipModel;
  }

  /**
   * @return int
   */
  public function getOldStatus() {
    return $this->oldStatus;
  }

  /**
   * @return int
   */
  public function getNewStatus() {
    return $this->newStatus;
  }

  public function isDelayed() {
    return $this->isDelayed ? true : false;
  }

}