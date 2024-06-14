<?php
/**
 * Copyright (C) 2024  Jaap Jansma (jaap.jansma@civicoop.org)
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

namespace Krabo\IsotopePackagingSlipBundle\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Database;
use Contao\Input;
use Contao\MemberModel;
use Contao\System;
use Dflydev\DotAccessData\Data;
use Doctrine\DBAL\Connection;
use Isotope\Model\ProductCollection\Order;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;

class MailController {

    public function sendEmails(int $limit = 25) {
        $objNotificationCollection = \NotificationCenter\Model\Notification::findByType('isotope_packaging_slip_mail');
        if (null === $objNotificationCollection) {
            return;
        }
        $processedIds = [];
        $db = Database::getInstance();
        $rows = $db->prepare("SELECT * FROM `tl_isotope_packaging_slip_mail_message` ORDER BY `id` ASC")->limit($limit)->execute();
        while($row = $rows->fetchAssoc()) {
            $packagingSlipModel = IsotopePackagingSlipModel::findByPk($row['pid']);
            $arrTokens = $packagingSlipModel->getNotificationTokens();
            $language = $GLOBALS['TL_LANGUAGE'];
            $emailLanguage = null;
            if ($packagingSlipModel->member) {
                $objMember = MemberModel::findOneBy('id', $packagingSlipModel->member);
                if ($objMember && !empty($objMember->language)) {
                  $emailLanguage = $objMember->language;
                }
            }
            if ($emailLanguage === null) {
              foreach($packagingSlipModel->getOrders() as $objOrder) {
                if (!empty($objOrder->language)) {
                  $emailLanguage = $objOrder->language;
                  break;
                }
              }
            }
            if ($emailLanguage === null) {
              $emailLanguage = 'nl_NL';
            }
            $GLOBALS['TL_LANGUAGE'] = $emailLanguage;
            $arrTokens['subject'] = $row['subject_nl'];
            $arrTokens['message'] = $row['msg_nl'];
            if ($GLOBALS['TL_LANGUAGE'] != 'nl' && $GLOBALS['TL_LANGUAGE'] != 'nl_NL') {
                $arrTokens['subject'] = $row['subject_en'];
                $arrTokens['message'] = $row['msg_en'];
            }
            $objNotificationCollection->reset();
            while ($objNotificationCollection->next()) {
                $objNotification = $objNotificationCollection->current();
                $objNotification->send($arrTokens, $GLOBALS['TL_LANGUAGE']);
            }
            $GLOBALS['TL_LANGUAGE'] = $language;
            $processedIds[] = $row['id'];
        }
        if (count($processedIds)) {
            $db->prepare("DELETE FROM `tl_isotope_packaging_slip_mail_message` WHERE `id` IN (" . implode(", ", $processedIds) . ")")->execute();
        }
    }

    public function prepareEmail(\DataContainer $dc) {
        \System::loadLanguageFile(IsotopePackagingSlipModel::getTable());
        \System::loadLanguageFile('tl_nc_language');
        \System::loadLanguageFile('default');
        \System::loadLanguageFile('tokens');
        \DataContainer::loadDataContainer(IsotopePackagingSlipModel::getTable(), false);

        $strBuffer = '';
        $values = array();
        $doNotSubmit = false;
        $objSession = System::getContainer()->get('session');
        $session = $objSession->all();
        $ids = array();
        if ($dc->id) {
            $ids[] = $dc->id;
        } elseif (isset($session['CURRENT']['IDS'])) {
            $ids = $session['CURRENT']['IDS'];
        }

        $arrSubjectNL = [
            'label'     => $GLOBALS['TL_LANG'][IsotopePackagingSlipModel::getTable()]['send_email_subject_nl'],
            'inputType' => 'text',
            'eval'      => array('mandatory'=>true, 'required' => true, 'rte'=>'tinyMCE'),
        ];
        $arrSubjectNlLidget = \Contao\TextField::getAttributesFromDca($arrSubjectNL, 'subject_nl');
        $objSubjectNLWidget = new \Contao\TextField($arrSubjectNlLidget);

        $arrSubjectEN = [
            'label'     => $GLOBALS['TL_LANG'][IsotopePackagingSlipModel::getTable()]['send_email_subject_en'],
            'inputType' => 'text',
            'eval'      => array('mandatory'=>true, 'required' => true, 'rte'=>'tinyMCE'),
        ];
        $arrSubjectENWidget = \Contao\TextField::getAttributesFromDca($arrSubjectEN, 'subject_en');
        $objSubjectENWidget = new \Contao\TextField($arrSubjectENWidget);

        $arrMessageNLField = [
            'label'     => $GLOBALS['TL_LANG'][IsotopePackagingSlipModel::getTable()]['send_email_message_nl'],
            'inputType' => 'textarea',
            'eval'      => array('mandatory'=>true, 'required' => true, 'rte'=>'tinyMCE'),
        ];
        $arrMessageNLWidget = \Contao\TextArea::getAttributesFromDca($arrMessageNLField, 'message_nl');
        $objMessageNLWidget = new \Contao\TextArea($arrMessageNLWidget);

        $arrMessageENField = [
            'label'     => $GLOBALS['TL_LANG'][IsotopePackagingSlipModel::getTable()]['send_email_message_en'],
            'inputType' => 'textarea',
            'eval'      => array('mandatory'=>true, 'required' => true, 'rte'=>'tinyMCE'),
        ];
        $arrMessageENWidget = \Contao\TextArea::getAttributesFromDca($arrMessageENField, 'message_en');
        $objMessageENWidget = new \Contao\TextArea($arrMessageENWidget);

        if (\Input::post('FORM_SUBMIT') === 'tl_isotope_packaging_slip_send_email') {
            $objSubjectNLWidget->validate();
            $objSubjectENWidget->validate();
            $objMessageNLWidget->validate();
            $objMessageENWidget->validate();

            if ($objSubjectNLWidget->hasErrors() || $objSubjectENWidget->hasErrors() || $objMessageNLWidget->hasErrors() || $objMessageENWidget->hasErrors()) {
                $doNotSubmit = true;
            } else {
                $values['subject_nl'] = $objSubjectNLWidget->value;
                $values['message_nl'] = $objMessageNLWidget->value;
                $values['subject_en'] = $objSubjectENWidget->value;
                $values['message_en'] = $objMessageENWidget->value;
            }
        }

        $strBuffer .= '<div class="clr widget">'.$objSubjectNLWidget->parse().'</div>';
        $strBuffer .= '<div class="clr widget">'.$objMessageNLWidget->parse().$this->addFileBrowser('ctrl_message_nl').'</div>';
        $strBuffer .= '<div class="clr widget">'.$objSubjectENWidget->parse().'</div>';
        $strBuffer .= '<div class="clr widget">'.$objMessageENWidget->parse().$this->addFileBrowser('ctrl_message_en').'</div>';

        if (\Input::post('FORM_SUBMIT') === 'tl_isotope_packaging_slip_send_email' && !$doNotSubmit) {
            /** @var Connection $connection */
            $connection = System::getContainer()->get('database_connection');
            $db = Database::getInstance();
            $sql = "INSERT INTO `tl_isotope_packaging_slip_mail_message` (`pid`, `subject_nl`, `subject_en`, `msg_nl`, `msg_en`, `tstamp`) VALUES ";
            $data = [];
            foreach($ids as $id) {
                $data[] = "(" . $connection->quote($id) . ', ' . $connection->quote($values['subject_nl']) . ', ' . $connection->quote($values['subject_en']) . ', ' . $connection->quote($values['message_nl']) . ', ' . $connection->quote($values['message_en']) . ', '. time() . ')';
            }
            if (count($data)) {
                $sql .= implode(", ", $data);
                $db->execute($sql);
            }

            $url = str_replace('&key=send_email', '', \Environment::get('request'));
            if (\Input::get('id') && Input::get('pid')) {
                $url = str_replace('&id='.\Input::get('id'), '&id='.\Input::get('pid'), $url);
                $url = str_replace('&pid='.\Input::get('pid'), '', $url);
            } elseif (Input::get('pid')) {
                $url = str_replace('&pid='.\Input::get('pid'), '&id='.\Input::get('pid'), $url);
            }
            \Controller::redirect($url);
        }

        return $this->output($strBuffer, count($ids));
    }

    private function output(string $strBuffer, int $count): string {
        return '
            <div id="tl_buttons">
              <a href="' . ampersand(str_replace('&key=send_email', '', \Environment::get('request'))) . '" class="header_back" title="' . specialchars($GLOBALS['TL_LANG']['MSC']['backBT']) . '">' . $GLOBALS['TL_LANG']['MSC']['backBT'] . '</a>
            </div>
            <h2 class="sub_headline">' . sprintf($GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['send_mail_headline'], $count) . '</h2>' . \Message::generate() . '
            <form action="' . ampersand(\Environment::get('request'), true) . '" id="tl_isotope_packaging_slip_send_email" class="tl_form" method="post">
                <div class="tl_formbody_edit">
                    <input type="hidden" name="FORM_SUBMIT" value="tl_isotope_packaging_slip_send_email">
                    <input type="hidden" name="REQUEST_TOKEN" value="' . REQUEST_TOKEN . '">
                    <fieldset class="tl_tbox block">
                    ' . $strBuffer . '
                    </fieldset>
                </div>
                <div class="tl_formbody_submit">
                    <div class="tl_submit_container">
                        <input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="' . specialchars($GLOBALS['TL_LANG']['tl_isotope_packaging_slip']['send_email'][1]) . '">
                    </div>
                </div>
            </form>';
    }

    private function addFileBrowser(string $selector) {
        $fileBrowserTypes = array();
        $pickerBuilder = System::getContainer()->get('contao.picker.builder');

        foreach (array('file' => 'image', 'link' => 'file') as $context => $fileBrowserType)
        {
            if ($pickerBuilder->supportsContext($context))
            {
                $fileBrowserTypes[] = $fileBrowserType;
            }
        }

        $objRteTemplate = new BackendTemplate('be_tinyMCE');
        $objRteTemplate->selector = $selector;
        $objRteTemplate->fileBrowserTypes = $fileBrowserTypes;
        // Deprecated since Contao 4.0, to be removed in Contao 5.0
        $objRteTemplate->language = Backend::getTinyMceLanguage();
        return $objRteTemplate->parse();
    }

}