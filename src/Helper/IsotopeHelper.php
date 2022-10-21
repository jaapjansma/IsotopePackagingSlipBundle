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

namespace Krabo\IsotopePackagingSlipBundle\Helper;

use Isotope\Model\Config;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;

class IsotopeHelper {

  /**
   * @param \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel $packagingSlipModel
   *
   * @return \Isotope\Model\Config
   */
  public static function getConfig(IsotopePackagingSlipModel $packagingSlipModel): Config {
    $config = null;
    $defaultConfig = \Isotope\Isotope::getConfig();
    if (!empty($packagingSlipModel->config_id)) {
      $config = \Isotope\Model\Config::findByPk($packagingSlipModel->config_id);
    }
    if (empty($config)) {
      $config = $defaultConfig;
    }
    return $config;
  }

}