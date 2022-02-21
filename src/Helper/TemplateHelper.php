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

use Isotope\Model\Shipping;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;

class TemplateHelper {

  public static function generatePackagingSlipHTML(IsotopePackagingSlipModel $packagingSlip, $template) {
    $objTemplate = new \Contao\FrontendTemplate($template);
    $objTemplate->setData([]);

    $shippingMethod = Shipping::findByPk($packagingSlip->shipping_id);
    $objTemplate->packagingSlip = $packagingSlip;
    $objTemplate->shipping = $shippingMethod;
    $objTemplate->dateFormat    = $GLOBALS['TL_CONFIG']['dateFormat'];
    $objTemplate->timeFormat    = $GLOBALS['TL_CONFIG']['timeFormat'];
    $objTemplate->datimFormat   = $GLOBALS['TL_CONFIG']['datimFormat'];
    return $objTemplate->parse();
  }

}