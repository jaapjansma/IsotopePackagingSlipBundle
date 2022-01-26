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

namespace Krabo\IsotopePackagingSlipBundle\Model\Shipping;

use Isotope\Frontend;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Shipping;
use Isotope\Model\Shipping\Flat;
use Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel;

class CombinePackagingSlip extends Flat {
  /**
   * @inheritdoc
   *
   * @throws \InvalidArgumentException on unknown quantity mode
   * @throws \UnexpectedValueException on unknown product type condition
   */
  public function isAvailable()
  {
    $combined_packaging_slip_id = Isotope::getCart()->combined_packaging_slip_id;
    if (!empty($combined_packaging_slip_id)) {
      return parent::isAvailable();
    }
    return FALSE;
  }

  /**
   * @inheritdoc
   */
  public function getNote()
  {
    $address = Isotope::getCart()->getShippingAddress()->generate(Isotope::getConfig()->getShippingFieldsConfig());
    $combined_packaging_slip_id = Isotope::getCart()->combined_packaging_slip_id;

    if ($combined_packaging_slip_id) {
      $objPackagingSlip = PackagingSlipModel::findOneBy('document_number', $combined_packaging_slip_id);
      $orderDocumentNumbers = [];
      foreach ($objPackagingSlip->getOrders() as $order) {
        $orderDocumentNumbers[] = $order->document_number;
      }
      return $this->note . '<div class="combined_packaging_slip_nr">' . implode('<br />', $orderDocumentNumbers).'</div><br />' . $address;
    }

    return $this->note . $address;
  }

  /**
   * Returns true when this Packaging Slip Model is available for this shipping method.
   *
   * @param \Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel $packagingSlipModel
   * @return bool
   */
  public function isAvailableForCombinedShipping(PackagingSlipModel $packagingSlipModel)
  {
    if (TL_MODE === 'BE') {
      return true;
    }

    if (!$this->enabled && BE_USER_LOGGED_IN !== true) {
      return false;
    }

    if (($this->guests && FE_USER_LOGGED_IN === true) || ($this->protected && FE_USER_LOGGED_IN !== true)) {
      return false;
    }

    if ($this->protected) {
      $arrGroups = deserialize($this->groups);

      if (!\is_array($arrGroups)
        || empty($arrGroups)
        || !\count(array_intersect($arrGroups, \FrontendUser::getInstance()->groups))
      ) {
        return false;
      }
    }

    if (($this->minimum_total > 0 && $this->minimum_total > Isotope::getCart()->getSubtotal())
      || ($this->maximum_total > 0 && $this->maximum_total < Isotope::getCart()->getSubtotal())
    ) {
      return false;
    }
    $arrCountries = deserialize($this->countries);
    if (\is_array($arrCountries) && !empty($arrCountries)) {
      if (!\in_array($packagingSlipModel->country, $arrCountries, true)) {
        return false;
      }
    }

    // Check if address has a valid postal code
    if ($this->postalCodes != '') {
      $arrCodes = Frontend::parsePostalCodes($this->postalCodes);

      if (!\in_array($packagingSlipModel->postal, $arrCodes)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Returns a list with possible orders to combine.
   *
   * @return array
   */
  public function getOptionsForCombinedPackagingSlips($arrFields = []) {
    $validStatuses = [
      1, // Open
    ];
    /** @var PackagingSlipModel[] $objPackagingSlips */
    $objPackagingSlips = PackagingSlipModel::findBy(
      [
        'status IN (' . implode(", ", $validStatuses). ')',
        'member=?'
      ],
      [\FrontendUser::getInstance()->id],
      ['order' => 'document_number ASC']
    );

    $return = [];
    if ($objPackagingSlips) {
      foreach ($objPackagingSlips as $objPackagingSlip) {
        if ($this->isAvailableForCombinedShipping($objPackagingSlip)) {
          try {
            $address = $objPackagingSlip->generateAddress();
            $default = '0';
            if (Isotope::getCart()->combined_packaging_slip_id && Isotope::getCart()->combined_packaging_slip_id == $objPackagingSlip->document_number) {
              $default = '1';
            }
            $orderDocumentNumbers = [];
            foreach($objPackagingSlip->getOrders() as $order) {
              $orderDocumentNumbers[] = $order->document_number;
            }
            $return['packaging_slip_' . $objPackagingSlip->id] = [
              'value' => 'packaging_slip_' . $objPackagingSlip->id,
              'label' => '<div class="packaging_slip_number">' . implode("<br>", $orderDocumentNumbers) . '</div><br />' . $address,
              'default' => $default,
            ];
          } catch (\Exception $ex) {
            // Do nothing.
          }
        }
      }
    }
    return $return;
  }

  /**
   * Get address object for a selected option
   *
   * @param mixed $varValue
   * @param bool  $blnValidate
   *
   * @return Address|null
   */
  public function getAddressForOption($varValue, $blnValidate) {
    if ($blnValidate) {
      Isotope::getCart()->combined_packaging_slip_id = '';
      Isotope::getCart()->save();
    }
    if (stripos($varValue, 'packaging_slip_') === 0) {
      $packagingSlipId = substr($varValue, 15);
      $packagingSlipModel = PackagingSlipModel::findOneBy('id', $packagingSlipId);
      $objAddress = Address::createForProductCollection(Isotope::getCart(), Isotope::getConfig()->getShippingFields(), false, false);
      foreach(Isotope::getConfig()->getShippingFields() as $field) {
        if (isset($packagingSlipModel->$field)) {
          $objAddress->$field = $packagingSlipModel->$field;
        }
      }
      $objAddress->save();
      Isotope::getCart()->setShippingMethod($this);
      Isotope::getCart()->combined_packaging_slip_id = $packagingSlipModel->document_number;
      if ($blnValidate) {
        Isotope::getCart()->setShippingAddress($objAddress);
        Isotope::getCart()->save();
      }
      return $objAddress;
    }
    return null;
  }

  public function skipShippingMethodSelection() {
    $combined_packaging_slip_id = Isotope::getCart()->combined_packaging_slip_id;
    if (!empty($combined_packaging_slip_id)) {
      return true;
    }
    return false;
  }


}