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

use Contao\Environment;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Isotope\Model\Config;
use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Helper\PackagingSlipCheckAvailability;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Model\Registry;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipProductCollectionModel;

\Contao\System::loadLanguageFile(\Isotope\Model\ProductCollection::getTable());
\Contao\Controller::loadDataContainer(\Isotope\Model\ProductCollection::getTable());

$GLOBALS['TL_DCA']['tl_isotope_packaging_slip'] = array
(
  // Config
  'config' => array
  (
    'dataContainer'             => 'Table',
    'switchToEdit'              => true,
    'sql'                       => array
    (
      'keys' => array
      (
        'id' => 'primary'
      )
    ),
    'onload_callback' => array(
      array('tl_isotope_packaging_slip', 'onLoad'),
    ),
    'onsubmit_callback' => array(
      array('tl_isotope_packaging_slip', 'onSubmit'),
    ),
    'ondelete_callback' => array(
      array('tl_isotope_packaging_slip', 'onDelete'),
    ),
    'oncopy_callback' => array(
      array('tl_isotope_packaging_slip', 'onCopy'),
    ),
  ),

  'select' => array(
    'buttons_callback' => array(
      array('tl_isotope_packaging_slip', 'selectButtonsCallback')
    ),
  ),

  // List
  'list' => array
  (
    'sorting' => array
    (
      'mode'                    => 2,
      'fields'                  => array('document_number'),
      'flag'                    => 11,
      'panelLayout'             => 'sort,limit,product_search,search,filter',
      'panel_callback'          => [
        'product_search'        => ['tl_isotope_packaging_slip', 'productSearchPanel'],
      ]
    ),
    'label' => array
    (
      'showColumns'             => true,
      'fields'                  => array('date', 'document_number', 'name', 'country', 'status', 'shipping_id', 'shipper_id', 'is_available', 'availability_notes', 'order_id'),
      'label_callback'          => ['tl_isotope_packaging_slip', 'labelCallback'],
    ),
    'global_operations' => array
    (
      'tl_isotope_packaging_slip_report' => array
      (
        'label'               =>  $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['viewReport'],
        'route'               => 'tl_isotope_packaging_slip_report',
        'class'               => 'tl_isotope_packaging_slip_report',
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="c"',
        'icon'                => 'tablewizard.svg',
      ),
      'all' => array
      (
        'href'                => 'act=select',
        'class'               => 'header_edit_all',
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
      ),
    ),
    'operations' => array
    (
      'edit' => array
      (
        'href'                => 'act=edit',
        'icon'                => 'edit.svg',
      ),
      'print_document' => array
      (
        'label'             => &$GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['print_document'],
        'href'              => 'key=print_document',
        'icon'              => 'bundles/isotopepackagingslip/document-pdf-text.png',
      ),
      'delete' => array
      (
        'href'                => 'act=delete',
        'icon'                => 'delete.svg',
        'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
      ),
    )
  ),

  // Palettes
  'palettes' => array
  (
    '__selector__'                => [],
    'default'                     => 'document_number,config_id;status,is_available;availability_notes,check_availability;date,scheduled_shipping_date,shipping_date;{stock_legend},credit_account,debit_account;{product_legend},product_id;{shipping_legend},shipping_id,shipper_id;{address_legend},member,firstname,lastname,email,phone,street_1,housenumber,street_2,street_3,postal,city,country;{notes_legend},notes,internal_notes'
  ),

  // Subpalettes
  'subpalettes' => array
  (
  ),

  // Fields
  'fields' => array
  (
    'id' => array
    (
      'sql'                     => "int(10) unsigned NOT NULL auto_increment"
    ),
    'tstamp' => array
    (
      'sql'                     => "int(10) unsigned NOT NULL default 0"
    ),
    'order_id' => array
    (
      // Only present to show a label in the overview table.
    ),
    'config_id' => array
    (
      'foreignKey'            => \Isotope\Model\Config::getTable().'.name',
      'sql'                   => "int(10) unsigned NOT NULL default '0'",
      'relation'              => array('type'=>'hasOne', 'load'=>'lazy'),
      'inputType'             => 'radio',
    ),
    'member'  =>  array
    (
      'search'                => true,
      'sql'                   => "int(10) unsigned NOT NULL default '0'",
      'inputType'             => 'tableLookup',
      'eval' => array
      (
        'mandatory'                 => false,
        'doNotSaveEmpty'            => true,
        'tl_class'                  => 'clr',
        'foreignTable'              => 'tl_member',
        'fieldType'                 => 'radio',
        'listFields'                => array('firstname', 'lastname', 'username', 'email'),
        'searchFields'              => array('firstname', 'lastname', 'username', 'email'),
        'sqlWhere'                  => '',
        'searchLabel'               => 'Search members',
      ),
    ),
    'status' => array
    (
      'filter'                  => true,
      'inputType'               => 'radio',
      'eval'                    => array('tl_class' => 'w50'),
      'reference'               => $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['status_options'],
      'options'                 => array('0', '1', '2', '3', '4', '5', '-1', '-2'),
      'sql'                     => "int(10) signed NOT NULL default 0",
      'default'                 => '0',
    ),
    'is_available' => array
    (
      'filter'                  => true,
      'inputType'               => 'radio',
      'eval'                    => array('tl_class' => 'w50'),
      'reference'               => $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['is_available_options'],
      'options'                 => array('0', '1', '-1'),
      'sql'                     => "int(10) signed NOT NULL default 0",
      'default'                 => '0',
    ),
    'check_availability' => array
    (
      'filter'                  => true,
      'inputType'               => 'checkbox',
      'eval'                    => array('doNotCopy'=>true),
      'sql'                     => "char(1) NOT NULL default '0'"
    ),
    'availability_notes' => array
    (
      'exclude'               => true,
      'inputType'             => 'textarea',
      'eval'                  => array('style'=>'height:80px;', 'tl_class' => 'clr'),
      'sql'                   => 'text NULL',
    ),
    'document_number' => array
    (
      'sorting'                 => true,
      'flag'                    => 12,
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('doNotCopy'=>true, 'readonly'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'date' => array
    (
      'filter'                  => true,
      'inputType'               => 'text',
      'sorting'                 => true,
      'flag'                    => 8,
      'default'                 => time(),
      'eval'                    => array('mandatory'=>true, 'rgxp'=>'date', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
      'sql'                     => "varchar(10) NOT NULL default ''"
    ),
    'scheduled_shipping_date' => array
    (
      'filter'                  => true,
      'inputType'               => 'text',
      'flag'                    => 8,
      'default'                 => time(),
      'eval'                    => array('mandatory'=>false, 'rgxp'=>'date', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
      'sql'                     => "varchar(10) NOT NULL default ''"
    ),
    'shipping_date' => array
    (
      'filter'                  => true,
      'inputType'               => 'text',
      'flag'                    => 8,
      'default'                 => time(),
      'eval'                    => array('mandatory'=>false, 'rgxp'=>'datim', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
      'sql'                     => "varchar(10) NOT NULL default ''"
    ),
    'fire_status_changed_event_on_shipping_date' => array(
      'inputType' => 'checkbox',
      'eval' => ['tl_class' => 'w50 clr', 'doNotCopy'=>true],
      'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => '']
    ),
    'old_status' => array
    (
      'inputType'               => 'radio',
      'eval'                    => array('doNotCopy'=>true, 'tl_class' => 'w50'),
      'reference'               => $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['status_options'],
      'options'                 => array('0', '1', '2', '3', '4', '5', '-1', '-2'),
      'sql'                     => "int(10) signed NULL",
      'default'                 => '0',
    ),
    'new_status' => array
    (
      'inputType'               => 'radio',
      'eval'                    => array('doNotCopy'=>true, 'tl_class' => 'w50'),
      'reference'               => $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['status_options'],
      'options'                 => array('0', '1', '2', '3', '4', '5', '-1', '-2'),
      'sql'                     => "int(10) signed NULL",
      'default'                 => '0',
    ),
    'product_id'     => array
    (
      'inputType'               => 'IsoPackagingSlipProductLookup',
      'eval' => array
      (
        'mandatory'                 => true,
        'doNotSaveEmpty'            => true,
        'submitOnChange'            => false,
        'tl_class'                  => 'clr',
      ),
    ),
    'name' => array
    (
    ),
    'firstname' => array
    (
      'exclude'               => true,
      'search'                => true,
      'inputType'             => 'text',
      'eval'                  => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                   => "varchar(255) NOT NULL default ''",
    ),
    'lastname' => array
    (
      'exclude'               => true,
      'search'                => true,
      'sorting'               => true,
      'flag'                  => 1,
      'inputType'             => 'text',
      'eval'                  => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                   => "varchar(255) NOT NULL default ''",
    ),
    'email' => array
    (
      'exclude'               => true,
      'search'                => true,
      'inputType'             => 'text',
      'eval'                  => array('mandatory'=>true, 'maxlength'=>255, 'rgxp'=>'email', 'tl_class'=>'w50'),
      'sql'                   => "varchar(255) NOT NULL default ''",
    ),
    'phone' => array
    (
      'exclude'               => true,
      'search'                => true,
      'inputType'             => 'text',
      'eval'                  => array('mandatory'=>false, 'maxlength'=>64, 'rgxp'=>'phone', 'tl_class'=>'w50'),
      'sql'                   => "varchar(255) NOT NULL default ''",
    ),
    'street_1' => array
    (
      'exclude'               => true,
      'search'                => true,
      'inputType'             => 'text',
      'eval'                  => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                   => "varchar(255) NOT NULL default ''",
    ),
    'street_2' => array
    (
      'exclude'               => true,
      'search'                => true,
      'inputType'             => 'text',
      'eval'                  => array('maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                   => "varchar(255) NOT NULL default ''",
    ),
    'housenumber' => array(
      'exclude'               => true,
      'search'                => true,
      'inputType'             => 'text',
      'eval'                  => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                   => "varchar(255) NOT NULL default ''",
    ),
    'street_3' => array
    (
      'exclude'               => true,
      'search'                => true,
      'inputType'             => 'text',
      'eval'                  => array('maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                   => "varchar(255) NOT NULL default ''",
    ),
    'postal' => array
    (
      'exclude'               => true,
      'search'                => true,
      'inputType'             => 'text',
      'eval'                  => array('mandatory'=>true, 'maxlength'=>32, 'tl_class'=>'clr w50'),
      'sql'                   => "varchar(32) NOT NULL default ''",
    ),
    'city' => array
    (
      'exclude'               => true,
      'filter'                => false,
      'search'                => true,
      'sorting'               => true,
      'inputType'             => 'text',
      'eval'                  => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                   => "varchar(255) NOT NULL default ''",
    ),
    'country' => array
    (
      'exclude'               => true,
      'filter'                => true,
      'sorting'               => true,
      'inputType'             => 'select',
      'options'               => \System::getCountries(),
      // Do not use options_callback, countries are modified by store config in the frontend
      'eval'                  => array('mandatory'=>true, 'tl_class'=>'w50', 'chosen'=>true),
      'sql'                   => "varchar(32) NOT NULL default ''",
    ),
    'shipping_id' => array
    (
      'filter'                => true,
      'inputType'             => 'select',
      'foreignKey'            => \Isotope\Model\Shipping::getTable().'.name',
      'sql'                   => "int(10) unsigned NOT NULL default '0'",
      'eval'                  => array('mandatory'=>true, 'tl_class'=>'w50', 'chosen'=>true),
      'relation'              => array('type'=>'hasOne', 'load'=>'lazy'),
    ),
    'shipper_id' => array
    (
      'filter'                => true,
      'inputType'             => 'select',
      'foreignKey'            => \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel::getTable().'.name',
      'sql'                   => "int(10) unsigned NOT NULL default '0'",
      'eval'                  => array('mandatory'=>false, 'tl_class'=>'w50', 'chosen'=>true, 'includeBlankOption' => true),
      'relation'              => array('type'=>'hasOne', 'load'=>'lazy'),
    ),
    'notes' => array
    (
      'exclude'               => true,
      'inputType'             => 'textarea',
      'eval'                  => array('style'=>'height:80px;', 'tl_class' => 'clr'),
      'sql'                   => 'text NULL',
    ),
    'internal_notes' => array
    (
      'exclude'               => true,
      'inputType'             => 'textarea',
      'eval'                  => array('style'=>'height:80px;', 'tl_class' => 'clr'),
      'sql'                   => 'text NULL',
    ),
    'credit_account' => array
    (
      'filter'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('tl_class' => 'w50'),
      'foreignKey'              => 'tl_isotope_stock_account.title',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'debit_account' => array
    (
      'filter'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('tl_class' => 'w50'),
      'foreignKey'              => 'tl_isotope_stock_account.title',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
  )
);

class tl_isotope_packaging_slip {

  protected $currentStatus;

  public function labelCallback($arrData, string $label, \Contao\DataContainer $dc, $labels) {
    /** @var \Symfony\Component\Routing\RouterInterface $router */
    $router = \Contao\System::getContainer()->get('router');
    $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
    $shipping_id_key = array_search('shipping_id', $fields, true);
    if ($labels[$shipping_id_key]) {
      $shippingMethod = \Isotope\Model\Shipping::findByPk($labels[$shipping_id_key]);
      $labels[$shipping_id_key] = $shippingMethod->name;
    }
    $shipper_id_key = array_search('shipper_id', $fields, true);
    if ($labels[$shipper_id_key]) {
      $shipper = \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel::findByPk($labels[$shipper_id_key]);
      $labels[$shipper_id_key] = $shipper->name;
    }
    $order_id_key = array_search('order_id', $fields, true);
    $order = \Database::getInstance()->prepare("
        SELECT `o`.`document_number`, `o`.`id` 
        FROM `tl_isotope_packaging_slip_product_collection` `p`
        INNER JOIN `tl_iso_product_collection` `o` ON `o`.`type` = 'order' AND `o`.`document_number` = `p`.`document_number`                                       
        WHERE `p`.`pid`= ? AND `p`.`document_number` != '' 
        GROUP BY `o`.`id`")->execute($arrData['id']);
    $orders = [];
    while($order->next()) {
      $order_url = $router->generate('contao_backend', ['act' => 'edit', 'do' => 'iso_orders', 'id' => $order->id, 'rt' => REQUEST_TOKEN]);
      $orders[] = '<a href="' . $order_url . '">' . $order->document_number . '</a>';
    }

    if (count($orders)) {
      $labels[$order_id_key] = implode(", ", $orders);
    }
    $name_id_key = array_search('name', $fields, true);
    $labels[$name_id_key] =trim($arrData['firstname'] . ' '.$arrData['lastname']);
    $document_number_key = array_search('document_number', $fields, true);
    if (!empty($arrData['check_availability'])) {
      $toCheckIcon = Contao\Image::getHtml('important.gif', $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['check_availability'][0], 'title="'.$GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['check_availability'][0].'"');
      $labels[$document_number_key] = $toCheckIcon.'&nbsp;' . $labels[$document_number_key];
    }
    return $labels;
  }

  public function onLoad(\Contao\DataContainer $dc) {
    if (Input::post('FORM_SUBMIT') == 'tl_select') {
      if (isset($_POST['printDocument']))
      {
        $dc->redirect(str_replace('act=select', 'key=print_documents', Environment::get('request')));
      }
      if (isset($_POST['checkAvailability']))
      {
        PackagingSlipCheckAvailability::checkAvailability();
        $dc->redirect(Environment::get('request'));
      }
    }

    /** @var AttributeBagInterface $objSessionBag */
    $objSessionBag = System::getContainer()->get('session')->getBag('contao_backend');
    $session = $objSessionBag->all();
    if (isset($session['product_search']) && isset($session['product_search'][$dc->strTable]) && isset($session['product_search'][$dc->strTable]['value'])) {
      $objResult = \Database::getInstance()->prepare("
        SELECT `tl_isotope_packaging_slip_product_collection`.`pid` 
        FROM `tl_isotope_packaging_slip_product_collection` 
        INNER JOIN `tl_iso_product` ON `tl_isotope_packaging_slip_product_collection`.`product_id` = `tl_iso_product`.`id`
        WHERE `tl_iso_product`.`sku` = ?
        GROUP BY `tl_isotope_packaging_slip_product_collection`.`pid`")->execute($session['product_search'][$dc->strTable]['value']);
      $dc->root = $objResult->fetchEach('pid');
    }

    $packagingSlip = IsotopePackagingSlipModel::findByPk($dc->id);
    $this->currentStatus = $packagingSlip->status;
    if (Input::get('order_id') && $dc instanceof \Contao\DC_Table) {
      /** @var AttributeBagInterface $objSessionBag */
      $objSessionBag = System::getContainer()->get('session')->getBag('contao_backend');
      $session = $objSessionBag->all();
      unset($session['search']['tl_isotope_packaging_slip']);
      $objSessionBag->replace($session);

      // Retrieve ids of the package slips attached to the order.
      $ids = [];
      $order = \Isotope\Model\ProductCollection\Order::findByPk(Input::get('order_id'));
      $packagingSlips = \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel::findPackagingSlipsByOrder($order);
      foreach ($packagingSlips as $packagingSlip) {
        $ids[] = $packagingSlip->id;
      }
      $dc->root = $ids;
    }
  }

  public function productSearchPanel(\Contao\DataContainer $dc) {
    /** @var AttributeBagInterface $objSessionBag */
    $objSessionBag = System::getContainer()->get('session')->getBag('contao_backend');
    $session = $objSessionBag->all();
    // Store search value in the current session
    if (Input::post('FORM_SUBMIT') == 'tl_filters')
    {
      $strKeyword = ltrim(Input::postRaw('product_search'), '*');
      $session['product_search'][$dc->strTable]['value'] = $strKeyword;

      $objSessionBag->replace($session);
    }

    return '
<div class="tl_search tl_subpanel" style="width: 100%;">
<strong>' . $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['product_search'] . ':</strong>
<input type="search" name="product_search" class="tl_text" value="' . StringUtil::specialchars($session['product_search'][$dc->strTable]['value']) . '">
</div>';
  }

  public function onSubmit(\Contao\DataContainer $dc) {
    $packagingSlip = IsotopePackagingSlipModel::findByPk($dc->id);

    if ($this->currentStatus === null) {
      $this->currentStatus = $packagingSlip->status;
    }

    $config = Config::findByPk($packagingSlip->config_id);
    if (empty($packagingSlip->document_number) && $config) {
      $prefix = $config->packagingSlipPrefix;
      if (empty($prefix)) {
        $prefix = $config->orderPrefix;
      }
      $packagingSlip->generateDocumentNumber($prefix, $config->orderDigits);
    }
    if ($dc->activeRecord->status != $this->currentStatus) {
      $packagingSlip->triggerStatusChangedEvent($this->currentStatus, $dc->activeRecord->status);
    }
    if ($dc->activeRecord->check_availability) {
      PackagingSlipCheckAvailability::resetAvailabilityStatus([$dc->id]);
    }
  }

  public function onDelete(\Contao\DataContainer $dc, $id) {
    $db = \Database::getInstance();
    $db->prepare("DELETE FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid` = ?")->execute($id);
  }

  public function onCopy($newId, \Contao\DataContainer $dc) {
    $oldId = $dc->id;
    $products = IsotopePackagingSlipProductCollectionModel::findBy('pid', $oldId);
    if ($products) {
      foreach($products as $product) {
        Registry::getInstance()->reset();
        $newProduct = clone $product;
        unset($newProduct->id);
        $newProduct->pid = $newId;
        $newProduct->save();
      }
    }
  }

  public function selectButtonsCallback($arrButtons, \Contao\DataContainer $dc) {
    unset($arrButtons['copy']);
    unset($arrButtons['override']);
    $arrButtons['checkAvailability'] = '<button type="submit" name="checkAvailability" id="checkAvailability" class="tl_submit">' . $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['check_availability'][0] . '</button>';
    $arrButtons['printDocument'] = '<button type="submit" name="printDocument" id="printDocument" class="tl_submit">' . $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['print_document'][0] . '</button>';
    return $arrButtons;
  }

}