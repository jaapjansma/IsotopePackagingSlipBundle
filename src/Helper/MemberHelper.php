<?php
/**
 * Copyright (C) 2025  Jaap Jansma (jaap.jansma@civicoop.org)
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

use Contao\Database;
use Contao\DataContainer;

class MemberHelper {

  public static function onSaveCallBack($newValue, DataContainer $dc) {
    if (empty($newValue) && $dc->activeRecord->isotope_packaging_slip_on_hold) {
      $result = Database::getInstance()->execute("SELECT id FROM `tl_isotope_packaging_slip` WHERE `member` = ".$dc->id." AND `is_available` = '-1' AND `status` = 0");
      $pids = [];
      while ($row = $result->next()) {
        $pids[] = $row->id;
      }
      PackagingSlipCheckAvailability::resetAvailabilityStatus($pids);
    }
    return $newValue;
  }

}