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
use Contao\Message;

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
      'mode'                    => 1,
      'fields'                  => array('date','tstamp'),
      'flag'                    => 12,
      'panelLayout'             => 'sort,filter,search,limit'
    ),
    'label' => array
    (
      'showColumns'             => true,
      'fields'                  => array('date', 'document_number', 'status', 'is_available', 'availability_notes'),
    ),
    'global_operations' => array
    (
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
    'default'                     => 'document_number;status,is_available;availability_notes;date;{stock_legend},credit_account,debit_account;{order_legend},order_id;product_id;{shipping_legend},shipping_id;{address_legend},member,firstname,lastname,street_1,housenumber,street_2,street_3,postal,city,country;{notes_legend},notes'
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
    'config_id' => array
    (
      'foreignKey'            => \Isotope\Model\Config::getTable().'.name',
      'sql'                   => "int(10) unsigned NOT NULL default '0'",
      'relation'              => array('type'=>'hasOne', 'load'=>'lazy'),
    ),
    'member'  =>  array
    (
      'search'                => true,
      //'foreignKey'            => "tl_member.CONCAT_WS(' ', company, firstname, lastname, street, postal, city)",
      'sql'                   => "int(10) unsigned NOT NULL default '0'",
      //'relation'              => array('type'=>'hasOne', 'load'=>'lazy'),
      'inputType'                     => 'tableLookup',
      'eval' => array
      (
        'mandatory'                 => true,
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
      'eval'                    => array('doNotCopy'=>true, 'tl_class' => 'w50'),
      'reference'               => $GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['status_options'],
      'options'                 => array('0', '1', '2'),
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
    'availability_notes' => array
    (
      'exclude'               => true,
      'inputType'             => 'textarea',
      'eval'                  => array('style'=>'height:80px;', 'tl_class' => 'clr'),
      'sql'                   => 'text NULL',
    ),
    'document_number' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'date' => array
    (
      'filter'                  => true,
      'inputType'               => 'text',
      'flag'                    => 8,
      'default'                 => time(),
      'eval'                    => array('mandatory'=>true, 'rgxp'=>'date', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
      'sql'                     => "varchar(10) NOT NULL default ''"
    ),
    'order_id'     => array
    (
      'inputType'               => 'tableLookup',
      'eval' => array
      (
        'mandatory'                 => true,
        'doNotSaveEmpty'            => true,
        'tl_class'                  => 'clr',
        'foreignTable'              => 'tl_iso_product_collection',
        'fieldType'                 => 'checkbox',
        'listFields'                => array(\Isotope\Model\ProductCollection::getTable().'.document_number'),
        'joins'                     => array(),
        'searchFields'              => array('document_number'),
        'customLabels'              => array
        (
          $GLOBALS['TL_DCA'][\Isotope\Model\ProductCollection::getTable()]['fields']['document_number']['label'][0],
        ),
        'sqlWhere'                  => 'type=\'order\' AND locked>0',
        'searchLabel'               => 'Search Order',
      ),
      'load_callback' => array
      (
        array('tl_isotope_packaging_slip', 'loadOrders'),
      ),
      'save_callback' => array
      (
        array('tl_isotope_packaging_slip', 'saveOrders'),
      ),
    ),
    'product_id'     => array
    (
      'inputType'               => 'tableLookup',
      'eval' => array
      (
        'mandatory'                 => true,
        'doNotSaveEmpty'            => true,
        'submitOnChange'            => true,
        'tl_class'                  => 'clr',
        'foreignTable'              => 'tl_iso_product',
        'fieldType'                 => 'checkbox',
        'listFields'                => array(\Isotope\Model\ProductType::getTable().'.name', 'name', 'sku'),
        'joins'                     => array
        (
          \Isotope\Model\ProductType::getTable() => array
          (
            'type' => 'LEFT JOIN',
            'jkey' => 'id',
            'fkey' => 'type',
          ),
        ),
        'searchFields'              => array('name', 'alias', 'sku', 'description'),
        'customLabels'              => array
        (
          $GLOBALS['TL_DCA'][\Isotope\Model\Product::getTable()]['fields']['type']['label'][0],
          $GLOBALS['TL_DCA'][\Isotope\Model\Product::getTable()]['fields']['name']['label'][0],
          $GLOBALS['TL_DCA'][\Isotope\Model\Product::getTable()]['fields']['sku']['label'][0],
        ),
        'sqlWhere'                  => 'pid=0',
        'searchLabel'               => 'Search Product',
        'customTpl'                 => 'be_widget_tablelookupwizard_product_id',
        'customContentTpl'          => 'be_widget_tablelookupwizard_product_id_content',
      ),
      'load_callback' => array
      (
        array('tl_isotope_packaging_slip', 'loadProducts'),
      ),
      'save_callback' => array
      (
        array('tl_isotope_packaging_slip', 'saveProducts'),
      ),
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
      'filter'                => true,
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
    'notes' => array
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
      'eval'                    => array('doNotCopy'=>true, 'tl_class' => 'w50'),
      'foreignKey'              => 'tl_isotope_stock_account.title',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'debit_account' => array
    (
      'filter'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('doNotCopy'=>true, 'tl_class' => 'w50'),
      'foreignKey'              => 'tl_isotope_stock_account.title',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
  )
);

class tl_isotope_packaging_slip {

  protected $currentStatus;

  public function loadOrders($varValue, $dc)
  {
    $varValue = \Database::getInstance()->execute("SELECT `order_id` FROM `tl_isotope_packaging_slip_order_collection` WHERE `pid`={$dc->activeRecord->id}")->fetchEach('order_id');

    if ($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['csv'] != '') {
      $varValue = implode($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['csv'], $varValue);
    }

    return $varValue;
  }

  public function saveOrders($varValue, $dc)
  {
    if ($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['csv'] != '') {
      $arrNew = explode($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['csv'], $varValue);
    } else {
      $arrNew = deserialize($varValue);
    }
    \Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel::saveOrders($dc->activeRecord->id, $arrNew);
    return '';
  }

  public function loadProducts($varValue, $dc)
  {
    $varValue = \Database::getInstance()->execute("SELECT `product_id`, `quantity` FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid`={$dc->activeRecord->id}")->fetchEach('product_id');

    if ($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['csv'] != '') {
      $varValue = implode($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['csv'], $varValue);
    }

    return $varValue;
  }

  public function saveProducts($varValue, $dc)
  {
    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    $requestStack = \Contao\System::getContainer()->get('request_stack');
    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    $request = $requestStack->getCurrentRequest();

    if ($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['csv'] != '') {
      $arrNew = explode($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['csv'], $varValue);
    } else {
      $arrNew = deserialize($varValue);
    }
    $products = [];
    foreach($arrNew as $product_id) {
      $key = 'product_id_quantity_' . $product_id;
      $products[$product_id] = $request->request->get($key);
    }
    \Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel::saveProducts($dc->activeRecord->id, $products);
    return '';
  }

  /**
   * Fetch quantity for product.
   *
   * @param $product_id
   *
   * @return bool|int|mixed|string|null
   */
  public static function fetchProductQuantity($product_id) {
    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    $requestStack = \Contao\System::getContainer()->get('request_stack');
    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    $request = $requestStack->getCurrentRequest();
    $id = $request->get('id');
    if ($id) {
      $sql = "SELECT `quantity` FROM `tl_isotope_packaging_slip_product_collection` WHERE `pid` = ? AND `product_id` = ?";
      $result = \Database::getInstance()->prepare($sql)->execute($id, $product_id);
      if ($result->count()) {
        return $result->first()->quantity;
      }
    }
    return 1;
  }

  public function onLoad(\Contao\DataContainer $dc) {
    if (Input::post('FORM_SUBMIT') == 'tl_select') {
      if (isset($_POST['printDocument']))
      {
        $dc->redirect(str_replace('act=select', 'key=print_documents', Environment::get('request')));
      }
      if (isset($_POST['checkAvailability']))
      {
        \Krabo\IsotopePackagingSlipBundle\Helper\PackagingSlipCheckAvailability::checkAvailability($dc);
        $dc->redirect(Environment::get('request'));
      }
    }
    $packagingSlip = \Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel::findByPk($dc->id);
    $this->currentStatus = $packagingSlip->status;
  }

  public function onSubmit(\Contao\DataContainer $dc) {
    if ($dc->activeRecord->status == 1 && $dc->activeRecord->status != $this->currentStatus) {
      $packagingSlip = \Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel::findByPk($dc->id);
      $packagingSlip->updateStock();
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