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

namespace Krabo\IsotopePackagingSlipBundle\Cron;

use Contao\CoreBundle\ServiceAnnotation\CronJob;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;

/**
 * @CronJob("minutely")
 */
class ProcessDelayedStatusChangedEventsCron {

  public function __invoke(): void
  {
    $t = IsotopePackagingSlipModel::getTable();
    $options = [
      'column' => array("$t.fire_status_changed_event_on_shipping_date=1", "$t.shipping_date!=''", "$t.shipping_date<=UNIX_TIMESTAMP()"),
      'order'  => "$t.tstamp",
      'limit'  => 50,
    ];
    $packagingSlips = IsotopePackagingSlipModel::findAll($options);
    if ($packagingSlips && $packagingSlips->count()) {
      foreach ($packagingSlips as $packagingSlip) {
        /* @var IsotopePackagingSlipModel $packagingSlip */
        $packagingSlip->triggerStatusChangedEvent($packagingSlip->old_status, $packagingSlip->new_status, TRUE);
      }
    }
  }

}