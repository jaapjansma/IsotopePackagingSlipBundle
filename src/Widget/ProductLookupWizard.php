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

namespace Krabo\IsotopePackagingSlipBundle\Widget;

use Contao\Model\Collection;
use Contao\Model\Registry;
use Contao\StringUtil;
use Isotope\Model\Product;
use Isotope\Model\ProductType;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollectionItem;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipProductCollectionModel;

class ProductLookupWizard extends \TableLookupWizard {

  public function __construct($arrAttributes = NULL) {
    parent::__construct($arrAttributes);
    $this->searchFields = [
      'name',
      'sku',
      ProductCollection::getTable() . '.document_number'
    ];
    $this->foreignTable = Product::getTable();
    $this->listFields = [
      ProductType::getTable().'.name',
      'name',
      'sku'
    ];
    $this->joins= [
      ProductType::getTable() =>
      [
        'type' => 'LEFT JOIN',
        'jkey' => 'id',
        'fkey' => Product::getTable() . '.type',
      ],
      ProductCollectionItem::getTable() =>
      [
        'type' => 'LEFT JOIN',
        'jkey' => 'product_id',
        'fkey' => Product::getTable() . '.id'
      ],
      ProductCollection::getTable() =>
      [
        'type' => 'LEFT JOIN',
        'jkey' => 'id',
        'fkey' => ProductCollectionItem::getTable().'.pid'
      ]
    ];
    $this->sqlOrderBy = ProductCollection::getTable() . '.document_number DESC, ' . Product::getTable() . '.sku ASC';
    $this->searchLabel = $GLOBALS['TL_LANG']['MSC']['PackageSlipProductLookup']['SearchLabel'];
    $this->customLabels = [
      $GLOBALS['TL_DCA'][Product::getTable()]['fields']['type']['label'][0],
      $GLOBALS['TL_DCA'][Product::getTable()]['fields']['name']['label'][0],
      $GLOBALS['TL_DCA'][Product::getTable()]['fields']['sku']['label'][0],
    ];
    $this->customTpl = 'be_widget_isopackagingslip_productlookup';
    $this->customContentTpl = 'be_widget_isopackagingslip_productlookup_content';
    $this->fieldType = 'checkbox';
    $this->blnEnableSorting = false;
    $this->intLimit = 5;
  }

  /**
   * Get the results.
   *
   * @return array
   */
  protected function getResults()
  {
    $arrResults = [];
    if ($this->blnIsAjaxRequest && \Input::get('keywords')) {
      $strKeys = [];
      if (\Input::get($this->strName)) {
        $strKeys = \Input::get($this->strName);
      }

      $objStatement = \Database::getInstance()
        ->prepare(implode(' ', $this->arrQueryProcedure));
      // Apply the limit only for the search results and not the current values

      $objStatement->limit($this->intLimit + 1);

      $objResults = $objStatement->execute($this->arrQueryValues);

      while ($objResults->next()) {
        $arrRow = $objResults->row();
        $strKeyWithoutOrder = $arrRow[$this->foreignTable . '_id'] . '_';
        if (!in_array($strKeyWithoutOrder, $strKeys)) {
          $arrResults[$strKeyWithoutOrder]['rowId'] = $arrRow[$this->foreignTable . '_id'];
          $arrResults[$strKeyWithoutOrder]['rawData'] = $arrRow;
          $arrResults[$strKeyWithoutOrder]['rawData']['quantity'] = 1;
          $arrResults[$strKeyWithoutOrder]['rawData']['value'] = '';
          $arrResults[$strKeyWithoutOrder]['rawData']['document_number'] = '';
        }

        $strKey = $arrRow[$this->foreignTable . '_id'] . '_' . $arrRow['tl_iso_product_collection_document_number'];
        if ($strKey != $strKeyWithoutOrder) {
          $arrResults[$strKey]['rowId'] = $arrRow[$this->foreignTable . '_id'];
          $arrResults[$strKey]['rawData'] = $arrRow;
          $arrResults[$strKey]['rawData']['quantity'] = $arrRow['tl_iso_product_collection_item_quantity'];
          $arrResults[$strKey]['rawData']['value'] = $arrRow['tl_iso_product_collection_item_quantity'] * $arrRow['tl_iso_product_collection_item_tax_free_price'];
          $arrResults[$strKey]['rawData']['document_number'] = $arrRow['tl_iso_product_collection_document_number'];
        }

        foreach ($this->arrListFields as $strField) {
          [$strTable, $strColumn] = explode('.', $strField);
          $strFieldKey = str_replace('.', '_', $strField);
          if (isset($arrResults[$strKeyWithoutOrder])) {
            $arrResults[$strKeyWithoutOrder]['formattedData'][$strFieldKey] = \Haste\Util\Format::dcaValue($strTable, $strColumn, $arrRow[$strFieldKey]);
          }
          if ($strKey != $strKeyWithoutOrder) {
            $arrResults[$strKey]['formattedData'][$strFieldKey] = \Haste\Util\Format::dcaValue($strTable, $strColumn, $arrRow[$strFieldKey]);
          }
        }
      }
    } else {
      $objResults = \Database::getInstance()->prepare("
        SELECT 
            tl_isotope_packaging_slip_product_collection.product_id as product_id,   
            SUM(tl_isotope_packaging_slip_product_collection.quantity) as quantity,
            SUM(tl_isotope_packaging_slip_product_collection.value) as `value`,
            tl_isotope_packaging_slip_product_collection.document_number as document_number,
            tl_iso_product.sku as tl_iso_product_sku,
            tl_iso_product.name as tl_iso_product_name,
            tl_iso_producttype.name as tl_iso_producttype_name    
        FROM `tl_isotope_packaging_slip_product_collection`
        INNER JOIN `tl_iso_product` ON tl_iso_product.id = tl_isotope_packaging_slip_product_collection.product_id AND tl_iso_product.pid = 0
        LEFT JOIN `tl_iso_producttype` ON tl_iso_producttype.id = tl_iso_product.type
        LEFT JOIN `tl_iso_product_collection` ON tl_iso_product_collection.document_number = tl_isotope_packaging_slip_product_collection.document_number AND LENGTH(tl_iso_product_collection.document_number) > 0
        LEFT JOIN `tl_iso_product_collection_item` ON tl_iso_product_collection_item.product_id = tl_iso_product.id AND `tl_iso_product_collection`.`id` = `tl_iso_product_collection_item`.`pid` 
        WHERE `tl_isotope_packaging_slip_product_collection`.`pid` = ?
        GROUP BY `tl_isotope_packaging_slip_product_collection`.`product_id`, `tl_isotope_packaging_slip_product_collection`.`document_number`")
        ->execute($this->activeRecord->id);
      while ($objResults->next()) {
        $arrRow = $objResults->row();
        $strKey = $arrRow['product_id'].'_'.$arrRow['document_number'];
        $arrResults[$strKey]['rowId'] = $arrRow['product_id'];
        $arrResults[$strKey]['rawData'] = $arrRow;
        $arrResults[$strKey]['isChecked'] = true;
        foreach ($this->arrListFields as $strField) {
          [$strTable, $strColumn] = explode('.', $strField);
          $strFieldKey = str_replace('.', '_', $strField);
          $arrResults[$strKey]['formattedData'][$strFieldKey] = \Haste\Util\Format::dcaValue($strTable, $strColumn, $arrRow[$strFieldKey]);
        }
      }
    }

    return $arrResults;
  }

  /**
   * Prepares the SELECT statement.
   */
  protected function prepareSelect()
  {
    $arrSelects = [$this->foreignTable.'.id AS '.$this->foreignTable.'_id'];

    foreach ($this->arrListFields as $strField) {
      $arrSelects[] = $strField.' AS '.str_replace('.', '_', $strField);
    }
    $arrSelects[] = 'tl_iso_product_collection_item.id AS tl_iso_product_collection_item_id';
    $arrSelects[] = 'tl_iso_product_collection_item.quantity AS tl_iso_product_collection_item_quantity';
    $arrSelects[] = 'tl_iso_product_collection_item.tax_free_price AS tl_iso_product_collection_item_tax_free_price';
    $arrSelects[] = 'tl_iso_product_collection.document_number AS tl_iso_product_collection_document_number';

    // Build SQL statement
    $this->arrQueryProcedure[] = 'SELECT '.implode(', ', $arrSelects);
    $this->arrQueryProcedure[] = 'FROM '.$this->foreignTable;
  }

  /**
   * Prepares the JOIN statement.
   */
  protected function prepareJoins()
  {
    if (!empty($this->arrJoins)) {
      foreach ($this->arrJoins as $k => $v) {
        $this->arrQueryProcedure[] = sprintf('%s %s ON %s.%s = %s', $v['type'], $k, $k, $v['jkey'], $v['fkey']);
      }
    }
  }

  /**
   * Prepares the WHERE statement.
   */
  protected function prepareWhere()
  {
    $arrKeywords = StringUtil::trimsplit(' ', \Input::get('keywords'));

    foreach ($arrKeywords as $strKeyword) {
      if (!$strKeyword) {
        continue;
      }
      $this->arrWhereProcedure[] = '('.implode(' LIKE ? OR ', $this->arrSearchFields).' LIKE ?)';
      $this->arrWhereValues = array_merge($this->arrWhereValues, array_fill(0, \count($this->arrSearchFields), '%'.$strKeyword.'%'));
    }
    $this->arrWhereProcedure[] = Product::getTable() . '.pid = 0';

    // If custom WHERE is set, add it to the statement
    if ($this->sqlWhere) {
      $this->arrWhereProcedure[] = $this->sqlWhere;
    }

    if (\Input::get($this->strName)) {
      $strKeys = \Input::get($this->strName);
      foreach($strKeys as $strKey) {
        [$product_id, $document_number] = explode("_", $strKey, 2);
        $this->arrWhereProcedure[] = "NOT (" . Product::getTable() . ".id = ? AND " . ProductCollection\Order::getTable() . ".document_number = ?)";
        $this->arrWhereValues[] = $product_id;
        $this->arrWhereValues[] = $document_number;
      }
    }

    if (!empty($this->arrWhereProcedure)) {
      $strWhere = implode(' AND ', $this->arrWhereProcedure);
      $this->arrQueryProcedure[] = 'WHERE '.$strWhere;
      $this->arrQueryValues = array_merge($this->arrQueryValues, $this->arrWhereValues);
    }
  }

  /**
   * Return true if the widgets submits user input
   *
   * @return boolean True if the widget submits user input
   */
  public function submitInput() {
    if (parent::submitInput()) {
      $this->saveProducts();
      return false;
    }
    return false;
  }

  /**
   * Save the products in the database.
   *
   * @param $varValue
   * @param $dc
   *
   * @return string
   */
  protected function saveProducts()
  {
    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    $requestStack = \Contao\System::getContainer()->get('request_stack');
    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    $request = $requestStack->getCurrentRequest();

    if ($this->csv != '') {
      $arrNew = explode($this->csv, $this->value);
    } else {
      $arrNew = StringUtil::deserialize($this->value);
    }
    $arrNew = array_unique($arrNew);
    $products = [];
    $packagingSlip = IsotopePackagingSlipModel::findByPk($this->activeRecord->id);
    foreach($arrNew as $strKey) {
      [$product_id, $document_number] = explode("_", $strKey, 2);
      $value = $request->request->get('product_id_value_' . $strKey);
      if ($value == '') {
        $value = 0.00;
      }
      $product = new IsotopePackagingSlipProductCollectionModel();
      $product->pid = $packagingSlip->pid;
      $product->product_id = $product_id;
      $product->quantity = $request->request->get('product_id_quantity_' . $strKey);
      $product->document_number = $request->request->get('product_id_document_number_' . $strKey);
      $product->value = $value;
      $products[] = $product;
    }
    IsotopePackagingSlipProductCollectionModel::saveProducts($packagingSlip, $products, false);
    return '';
  }

}