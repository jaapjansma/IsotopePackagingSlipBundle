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

use Haste\Util\Format;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;

class AddressHelper {


  /**
   * Compile the list of hCard tokens for this address
   *
   * @param array $arrFields
   *
   * @return array
   */
  public static function getAddressTokens(IsotopePackagingSlipModel $packagingSlipModel)
  {
    $arrFields = [
      'firstname',
      'lastname',
      'street_1',
      'housenumber',
      'street_2',
      'street_3',
      'postal',
      'city',
      'country'
    ];
    $arrTokens = array('outputFormat' => 'html');

    foreach ($arrFields as $strField) {
      $arrTokens[$strField] = Format::dcaValue(IsotopePackagingSlipModel::getTable(), $strField, $packagingSlipModel->$strField);
    }
    /**
     * Generate hCard fields
     * See http://microformats.org/wiki/hcard
     */
    $fn        = trim($arrTokens['firstname'] . ' ' . $arrTokens['lastname']);
    $street = implode('<br>', array_filter([$packagingSlipModel->street_1, $packagingSlipModel->street_2, $packagingSlipModel->street_3]));
    $arrTokens += [
      'hcard_honorific_prefix' => '',
      'hcard_tel'              => '',
      'hcard_email'            => '',
      'hcard_fn'               => $fn ? '<span class="fn">' . $fn . '</span>' : '',
      'hcard_n'                => ($arrTokens['firstname'] || $arrTokens['lastname']) ? '1' : '',
      'hcard_given_name'       => $arrTokens['firstname'] ? '<span class="given-name">' . $arrTokens['firstname'] . '</span>' : '',
      'hcard_family_name'      => $arrTokens['lastname'] ? '<span class="family-name">' . $arrTokens['lastname'] . '</span>' : '',
      'hcard_adr'              => ($street || $arrTokens['city'] || $arrTokens['postal'] || $arrTokens['country']) ? '1' : '',
      'hcard_street_address'   => $street ? '<div class="street-address">' . $street . '</div>' : '',
      'hcard_locality'         => $arrTokens['city'] ? '<span class="locality">' . $arrTokens['city'] . '</span>' : '',
      'hcard_postal_code'      => $arrTokens['postal'] ? '<span class="postal-code">' . $arrTokens['postal'] . '</span>' : '',
      'hcard_country_name'     => $arrTokens['country'] ? '<div class="country-name">' . $arrTokens['country'] . '</div>' : '',
    ];

    return $arrTokens;
  }


}