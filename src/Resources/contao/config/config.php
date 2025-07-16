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
use \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel;
use \Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipProductCollectionModel;

array_insert($GLOBALS['BE_MOD']['isotope'], 2, array
(
  'tl_isotope_packaging_slip' => array
  (
    'tables'            => array('tl_isotope_packaging_slip'),
    'print_document'    => array('Krabo\IsotopePackagingSlipBundle\Backend\PackagingSlipDocument', 'printDocument'),
    'print_documents'   => array('Krabo\IsotopePackagingSlipBundle\Backend\PackagingSlipDocument', 'printMultipleDocuments'),
    'send_email'        => array('Krabo\IsotopePackagingSlipBundle\Controller\MailController', 'prepareEmail'),
  ),
));

$GLOBALS['ISO_MOD']['checkout']['packaging_slip_shipper'] = [
  'tables'        => array(IsotopePackagingSlipShipperModel::getTable()),
  'icon'          => 'system/modules/isotope/assets/images/setup-shipping.png',
];

$GLOBALS['TL_MODELS']['tl_isotope_packaging_slip'] = IsotopePackagingSlipModel::class;
$GLOBALS['TL_MODELS']['tl_isotope_packaging_slip_shipper'] = IsotopePackagingSlipShipperModel::class;
$GLOBALS['TL_MODELS']['tl_isotope_packaging_slip_product_collection'] = IsotopePackagingSlipProductCollectionModel::class;

$GLOBALS['ISO_HOOKS']['preOrderStatusUpdate'][] = [ProductCollectionListener::class, 'preOrderStatusUpdate'];
$GLOBALS['ISO_HOOKS']['postOrderStatusUpdate'][] = [ProductCollectionListener::class, 'postOrderStatusUpdate'];
$GLOBALS['ISO_HOOKS']['createFromProductCollection'][] = [ProductCollectionListener::class, 'createFromProductCollection'];
$GLOBALS['ISO_HOOKS']['updateDraftOrder'][] = [ProductCollectionListener::class, 'updateDraftOrder'];
$GLOBALS['ISO_HOOKS']['getOrderNotificationTokens'][] = [ProductCollectionListener::class, 'getOrderNotificationTokens'];

\Isotope\Model\Shipping::registerModelType('combine_packaging_slip', 'Krabo\IsotopePackagingSlipBundle\Model\Shipping\CombinePackagingSlip');

$GLOBALS['BE_FFL']['IsoPackagingSlipProductLookup'] = 'Krabo\IsotopePackagingSlipBundle\Widget\ProductLookupWizard';

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope']['iso_order_status_change']['email_text'][] = 'packaging_slip_shipping_date';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope']['iso_order_status_change']['email_text'][] = 'packaging_slip_scheduled_shipping_date';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope']['iso_order_status_change']['email_text'][] = 'packaging_slip_trackandtrace';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope']['iso_order_status_change']['email_text'][] = 'packaging_slip_trackandtrace_code';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope']['iso_order_status_change']['email_text'][] = 'packaging_slip_shipper';
/**
 * Notification Center notification types
 */
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['recipients'] = array('recipient_email');
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['email_text'] = array(
  'id',
  'status',
  'status_old',
  'status_id',
  'status_id_old',
  'recipient_email',
  'shipping_date',
  'firstname',
  'lastname',
  'email',
  'phone',
  'housenumber',
  'street_1',
  'street_2',
  'street_3',
  'postal',
  'city',
  'country',
  'notes',
  'id',
  'document_number',
  'document',
  'shipping_id', // Shipping method ID
  'shipping_label', // Shipping method label
  'shipping_note', // Shipping method note
  'shipping_method_*', // All shipping method fields
  'packaging_slip_*', // All Packaging Slip method fields
);
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['email_subject'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['email_html'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['email_replyTo'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['email_recipient_cc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['email_recipient_bcc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1']['recipients'];

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_2'] = $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_3'] = $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_4'] = $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_5'] = $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_1'];

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['recipients'] = array('recipient_email');
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['email_text'] = array(
  'id',
  'status',
  'status_id',
  'recipient_email',
  'shipping_date',
  'firstname',
  'lastname',
  'email',
  'phone',
  'housenumber',
  'street_1',
  'street_2',
  'street_3',
  'postal',
  'city',
  'country',
  'notes',
  'id',
  'document_number',
  'document',
  'shipping_id', // Shipping method ID
  'shipping_label', // Shipping method label
  'shipping_note', // Shipping method note
  'shipping_method_*', // All shipping method fields
  'packaging_slip_*', // All Packaging Slip method fields
);
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['email_subject'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['email_html'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['email_replyTo'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['email_recipient_cc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['email_recipient_bcc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_status_on_hold']['recipients'];

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['recipients'] = array('recipient_email');
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['email_text'] = array(
    'id',
    'status',
    'status_id',
    'recipient_email',
    'shipping_date',
    'firstname',
    'lastname',
    'email',
    'phone',
    'housenumber',
    'street_1',
    'street_2',
    'street_3',
    'postal',
    'city',
    'country',
    'notes',
    'id',
    'document_number',
    'document',
    'shipping_id', // Shipping method ID
    'shipping_label', // Shipping method label
    'shipping_note', // Shipping method note
    'shipping_method_*', // All shipping method fields
    'packaging_slip_*', // All Packaging Slip method fields
    'subject',
    'message'
);
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['email_subject'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['email_html'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['email_replyTo'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['email_recipient_cc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['recipients'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['email_recipient_bcc'] = &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['isotope_packaging_slip']['isotope_packaging_slip_mail']['recipients'];