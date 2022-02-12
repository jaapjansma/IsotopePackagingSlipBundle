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

use \Krabo\IsotopePackagingSlipBundle\EventListener\ProductCollectionListener;
use \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipProductCollectionModel;

array_insert($GLOBALS['BE_MOD']['isotope'], 2, array
(
  'tl_isotope_packaging_slip' => array
  (
    'tables'            => array('tl_isotope_packaging_slip'),
    'print_document'    => array('Krabo\IsotopePackagingSlipBundle\Backend\PackagingSlipDocument', 'printDocument'),
    'print_documents' => array('Krabo\IsotopePackagingSlipBundle\Backend\PackagingSlipDocument', 'printMultipleDocuments'),
  ),
));

$GLOBALS['TL_MODELS']['tl_isotope_packaging_slip'] = IsotopePackagingSlipModel::class;
$GLOBALS['TL_MODELS']['tl_isotope_packaging_slip_product_collection'] = IsotopePackagingSlipProductCollectionModel::class;

$GLOBALS['ISO_HOOKS']['postOrderStatusUpdate'][] = [ProductCollectionListener::class, 'postOrderStatusUpdate'];
$GLOBALS['ISO_HOOKS']['createFromProductCollection'][] = [ProductCollectionListener::class, 'createFromProductCollection'];

\Isotope\Model\Shipping::registerModelType('combine_packaging_slip', 'Krabo\IsotopePackagingSlipBundle\Model\Shipping\CombinePackagingSlip');

$GLOBALS['BE_FFL']['IsoPackagingSlipProductLookup'] = 'Krabo\IsotopePackagingSlipBundle\Widget\ProductLookupWizard';