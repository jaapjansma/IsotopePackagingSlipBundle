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

use Contao\StringUtil;
use Isotope\Model\Product;
use Isotope\Model\ProductType;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollectionItem;
use Krabo\IsotopePackagingSlipBundle\Helper\IsotopeHelper;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipProductCollectionModel;

class ProductLookupWizard extends \TableLookupWizard {

  protected $customTpl = 'be_widget_tablelookupwizard';

  /**
   * @var string
   */
  protected $customContentTpl = 'be_widget_tablelookupwizard_content';

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
          $arrResults[$strKeyWithoutOrder]['rawData']['options'] = '';
          $arrResults[$strKeyWithoutOrder]['rawData']['weight'] = IsotopePackagingSlipProductCollectionModel::getWeightForIsoProduct($arrRow['tl_iso_product_id']);
        }

        $strKey = $arrRow[$this->foreignTable . '_id'] . '_' . $arrRow['tl_iso_product_collection_document_number'];
        if ($strKey != $strKeyWithoutOrder) {
          $options = '';
          if (!empty($arrRow['tl_iso_product_collection_item_id'])) {
            $item = ProductCollectionItem::findByPk($arrRow['tl_iso_product_collection_item_id']);
            if ($item) {
              $options = IsotopeHelper::generateOptions($item);
            }
          }
          $strKey .= '_' . md5($options);

          $arrResults[$strKey]['rowId'] = $arrRow[$this->foreignTable . '_id'];
          $arrResults[$strKey]['rawData'] = $arrRow;
          $arrResults[$strKey]['rawData']['quantity'] = $arrRow['tl_iso_product_collection_item_quantity'];
          $arrResults[$strKey]['rawData']['value'] = $arrRow['tl_iso_product_collection_item_quantity'] * $arrRow['tl_iso_product_collection_item_tax_free_price'];
          $arrResults[$strKey]['rawData']['document_number'] = $arrRow['tl_iso_product_collection_document_number'];
          $arrResults[$strKey]['rawData']['options'] = $options;
          $arrResults[$strKey]['rawData']['weight'] = IsotopePackagingSlipProductCollectionModel::getWeightForIsoProduct($arrRow['tl_iso_product_id']);
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
            tl_isotope_packaging_slip_product_collection.options as options,
            SUM(tl_isotope_packaging_slip_product_collection.quantity) as quantity,
            SUM(tl_isotope_packaging_slip_product_collection.value) as `value`,
            MAX(`tl_isotope_packaging_slip_product_collection`.`weight`) as `weight`,
            tl_isotope_packaging_slip_product_collection.document_number as document_number,
            tl_iso_product.sku as tl_iso_product_sku,
            tl_iso_product.name as tl_iso_product_name,
            tl_iso_producttype.name as tl_iso_producttype_name    
        FROM `tl_isotope_packaging_slip_product_collection`
        INNER JOIN `tl_iso_product` ON tl_iso_product.id = tl_isotope_packaging_slip_product_collection.product_id AND tl_iso_product.pid = 0
        LEFT JOIN `tl_iso_producttype` ON tl_iso_producttype.id = tl_iso_product.type
        LEFT JOIN `tl_iso_product_collection` ON tl_iso_product_collection.document_number = tl_isotope_packaging_slip_product_collection.document_number AND LENGTH(tl_iso_product_collection.document_number) > 0 
        WHERE `tl_isotope_packaging_slip_product_collection`.`pid` = ?
        GROUP BY `tl_isotope_packaging_slip_product_collection`.`product_id`, `tl_isotope_packaging_slip_product_collection`.`document_number`, `tl_isotope_packaging_slip_product_collection`.`options`
        ORDER BY `weight` ASC")
        ->execute($this->activeRecord->id);
      while ($objResults->next()) {
        $arrRow = $objResults->row();
        $strKey = $arrRow['product_id'].'_'.$arrRow['document_number']. '_'.md5($arrRow['options']);
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

    $arrNew = $request->request->get($this->strId);
    if (!is_array($arrNew)) {
      $arrNew = [];
    }
    $arrNew = array_unique($arrNew);
    $products = [];
    $packagingSlip = IsotopePackagingSlipModel::findByPk($this->objDca->id);
    foreach ($arrNew as $strKey) {
      [$product_id, $document_number] = explode("_", $strKey, 2);
      $value = $request->request->get('product_id_value_' . $strKey);
      if ($value == '') {
        $value = 0.00;
      }
      $product = new IsotopePackagingSlipProductCollectionModel();
      $product->pid = $packagingSlip->id;
      $product->product_id = $product_id;
      $product->quantity = $request->request->get('product_id_quantity_' . $strKey);
      $product->document_number = $request->request->get('product_id_document_number_' . $strKey);
      $product->options = $request->request->get('product_id_options_' . $strKey);
      $product->value = $value;
      $product->weight = $request->request->get('product_id_weight_' . $strKey);
      $products[] = $product;
    }
    IsotopePackagingSlipProductCollectionModel::saveProducts($packagingSlip, $products, false);
    return '';
  }

  /**
   * Generate the widget and return it as string
   * @return  string
   */
  public function generate()
  {
    $blnNoAjax          = \Input::get('noajax');
    $arrIds             = deserialize($this->varValue, true);

    if ($arrIds[0] == '') {
      $arrIds = array(0);
    } else {
      $this->blnHasValues = true;
    }

    $this->blnIsAjaxRequest = \Input::get('tableLookupWizard') == $this->strId;

    // Ensure search and list fields have correct aliases
    $this->ensureColumnAliases($this->arrSearchFields);;
    $this->ensureColumnAliases($this->arrListFields);

    // Ajax call
    if ($this->blnIsAjaxRequest) {
      // Clean buffer
      while (ob_end_clean());

      $this->prepareSelect();
      $this->prepareJoins();
      $this->prepareWhere();
      $this->prepareOrderBy();
      $this->prepareGroupBy();

      $strBuffer = $this->getBody();
      $response = new \Haste\Http\Response\JsonResponse(array
      (
        'content'   => $strBuffer,
        'token'     => REQUEST_TOKEN,
      ));

      $response->send();
    }

    $GLOBALS['TL_CSS'][] = 'system/modules/tablelookupwizard/assets/tablelookup.min.css';

    if (!$blnNoAjax) {
      $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/tablelookupwizard/assets/tablelookup.min.js';
    }

    $this->prepareSelect();
    $this->prepareJoins();

    // Add preselect to WHERE statement
    $this->arrWhereProcedure[] = $this->foreignTable . '.id IN (' . implode(',', $arrIds) . ')';

    $this->prepareWhere();
    $this->prepareOrderBy();
    $this->prepareGroupBy();

    $objTemplate = new \BackendTemplate($this->customTpl);
    $objTemplate->noAjax            = $blnNoAjax;
    $objTemplate->strId             = $this->strId;
    $objTemplate->fieldType         = $this->fieldType;
    $objTemplate->fallbackEnabled   = $this->blnEnableFallback;
    $objTemplate->noAjaxUrl         = $this->addToUrl('noajax=1');
    $objTemplate->listFields        = $this->arrListFields;
    $objTemplate->colspan           = count($this->arrListFields) + (int) $this->blnEnableSorting;
    $objTemplate->searchLabel       = $this->searchLabel == '' ? $GLOBALS['TL_LANG']['MSC']['searchLabel'] : $this->searchLabel;
    $objTemplate->columnLabels      = $this->getColumnLabels();
    $objTemplate->hasValues         = $this->blnHasValues;
    $objTemplate->enableSorting     = $this->blnEnableSorting;
    $objTemplate->body              = $this->getBody();

    return $objTemplate->parse();
  }

  /**
   * Renders the table body
   * @return  string
   */
  public function getBody()
  {
    $objTemplate    = new \BackendTemplate($this->customContentTpl);
    $arrResults     = array();
    $blnQuery       = true;

    if ($this->blnIsAjaxRequest && !\Input::get('keywords')) {
      $blnQuery = false;
    }

    if ($blnQuery) {
      $arrResults = $this->getResults();

      \Haste\Generator\RowClass::withKey('rowClass')
        ->addCustom('row')
        ->addCount('row_')
        ->addFirstLast('row_')
        ->addEvenOdd('row_')
        ->applyTo($arrResults);
    }

    if (!empty($arrResults)) {
      $objTemplate->hasResults = true;
    }

    // Determine the results message based on keywords availability
    if (strlen(\Input::get('keywords'))) {
      $noResultsMessage = sprintf($GLOBALS['TL_LANG']['MSC']['tlwNoResults'], \Input::get('keywords'));
    } else {
      $noResultsMessage = $GLOBALS['TL_LANG']['MSC']['tlwNoValue'];
    }

    $objTemplate->results           = $arrResults;
    $objTemplate->colspan           = count($this->arrListFields) + 1 + (int) $this->blnEnableSorting;
    $objTemplate->noResultsMessage  = $noResultsMessage;
    $objTemplate->fieldType         = $this->fieldType;
    $objTemplate->isAjax            = $this->blnIsAjaxRequest;
    $objTemplate->strId             = $this->strId;
    $objTemplate->enableSorting     = $this->blnEnableSorting;
    $objTemplate->dragHandleIcon    = 'system/themes/' . \Backend::getTheme() . '/images/drag.gif';

    return $objTemplate->parse();
  }

}