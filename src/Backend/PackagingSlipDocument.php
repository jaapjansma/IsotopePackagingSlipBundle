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

namespace Krabo\IsotopePackagingSlipBundle\Backend;

use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\System;
use Haste\Util\StringUtil;
use Krabo\IsotopePackagingSlipBundle\Helper\StockBookingHelper;
use Krabo\IsotopePackagingSlipBundle\Helper\TemplateHelper;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Model\Registry;
use Symfony\Component\HttpFoundation\Session\Session;

class PackagingSlipDocument extends \Backend {

  protected $documentTpl = 'packaging_slip_document';

  protected $productListTpl = 'packaging_slip_product_list';

  /**
   * Pass an order to the document
   *
   * @param \DataContainer $dc
   *
   * @throws \Exception
   * @return string
   */
  public function printDocument(\DataContainer $dc) {
    $packagingSlip = IsotopePackagingSlipModel::findByPk($dc->id);
    $ids[] = $dc->id;
    $pdf = $this->createPdf($ids);
    $pdf->Output($this->prepareFileName($packagingSlip->getDocumentNumber()) . '.pdf', 'D');
  }

  public function printMultipleDocuments(\Contao\DataContainer $dc) {
    /** @var Session $objSession */
    $objSession = System::getContainer()->get('session');
    // Get current IDs from session
    $session = $objSession->all();
    $ids = $session['CURRENT']['IDS'];
    $pdf = $this->createPdf($ids);
    $pdf->Output($this->prepareFileName('packaging_slip') . '.pdf', 'D');
  }

  /**
   * @param $ids
   *
   * @return \Mpdf\Mpdf
   * @throws \Mpdf\MpdfException
   */
  protected function createPdf($ids) {
    $pdf        = $this->generatePDF();
    $pdf->SetHTMLFooter('{PAGENO}');
    $pdf->AddPage('P', '', '1', '', '', '10', '10', '10', '10');
    $pdf->writeHTML($this->generateProductListTemplate($ids));
    foreach($ids as $id) {
      $packagingSlip = IsotopePackagingSlipModel::findByPk($id);
      $pdf->AddPage('P', '', '1', '', '', '10', '10', '10', '10');
      $pdf->writeHTML($this->generateTemplate($packagingSlip));
      $validOldStatusIds = [
        IsotopePackagingSlipModel::STATUS_OPEN,
        IsotopePackagingSlipModel::STATUS_ONHOLD
      ];
      if (in_array($packagingSlip->status, $validOldStatusIds)) {
        $packagingSlip->status = IsotopePackagingSlipModel::STATUS_PREPARE_FOR_SHIPPING;
        $packagingSlip->save();
      }
    }
    return $pdf;
  }

  /**
   * Prepare file name
   *
   * @param string $strName   File name
   *
   * @return string Sanitized file name
   */
  protected function prepareFileName($strName)
  {
    // Replace simple tokens
    $strName = $this->sanitizeFileName(StringUtil::recursiveReplaceTokensAndTags($strName, StringUtil::NO_TAGS | StringUtil::NO_BREAKS | StringUtil::NO_ENTITIES));
    return $strName;
  }

  /**
   * Sanitize file name
   *
   * @param string $strName              File name
   * @param bool   $blnPreserveUppercase Preserve uppercase (true by default)
   *
   * @return string Sanitized file name
   */
  protected function sanitizeFileName($strName, $blnPreserveUppercase = true)
  {
    return standardize(ampersand($strName, false), $blnPreserveUppercase);
  }

  /**
   * Generate the pdf document
   *
   * @return \Mpdf\Mpdf
   */
  protected function generatePDF()
  {
    // Get the project directory
    $projectDir = System::getContainer()->getParameter('kernel.project_dir');

    // Include TCPDF config
    if (file_exists($projectDir.'/system/config/tcpdf.php')) {
      require_once $projectDir.'/system/config/tcpdf.php';
    } elseif (file_exists($projectDir.'/vendor/contao/core-bundle/src/Resources/contao/config/tcpdf.php')) {
      require_once $projectDir.'/vendor/contao/core-bundle/src/Resources/contao/config/tcpdf.php';
    } elseif (file_exists($projectDir.'/vendor/contao/tcpdf-bundle/src/Resources/contao/config/tcpdf.php')) {
      require_once $projectDir.'/vendor/contao/tcpdf-bundle/src/Resources/contao/config/tcpdf.php';
    }

    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    // Add custom fonts
    if ($this->useCustomFonts) {
      if (null !== ($folder = FilesModel::findByUuid($this->customFontsDirectory))) {
        $fontDirs[] = $projectDir.'/'.$folder->path;

        $config = \Contao\StringUtil::deserialize($this->customFontsConfig, true);
        if (!empty($config)) {
          foreach ($config as $font) {
            if (!empty($font['fontname']) && $font['enabled']) {
              $fontData[$font['fontname']][$font['variant']] = $font['filename'];
            }
          }
        }
      }
    }

    // Create new PDF document
    $pdf = new \Mpdf\Mpdf([
      'fontDir' => $fontDirs,
      'fontdata' => $fontData,
      'format' => \defined('PDF_PAGE_FORMAT') ? PDF_PAGE_FORMAT : 'A4',
      'orientation' => \defined('PDF_PAGE_ORIENTATION') ? PDF_PAGE_ORIENTATION : 'P',
      'default_font_size' => \defined('PDF_FONT_SIZE_MAIN') ? PDF_FONT_SIZE_MAIN : 12,
      'default_font' => \defined('PDF_FONT_NAME_MAIN') ? PDF_FONT_NAME_MAIN : 'freeserif',
    ]);

    // Set document information
    $pdf->SetCreator(\defined('PDF_CREATOR') ? PDF_CREATOR : 'Contao Open Source CMS');
    $pdf->SetAuthor(\defined('PDF_AUTHOR') ? PDF_AUTHOR : Environment::get('url'));
    return $pdf;
  }

  /**
   * Generate and return document template
   *
   * @param IsotopePackagingSlipModel $packagingSlip
   *
   * @return string
   */
  protected function generateTemplate(IsotopePackagingSlipModel $packagingSlip)
  {
    return $this->fixTemplateOutput(TemplateHelper::generatePackagingSlipHTML($packagingSlip, $this->documentTpl));
  }

  /**
   * Generate and return document template
   *
   * @param IsotopePackagingSlipModel $packagingSlip
   *
   * @return string
   */
  protected function generateProductListTemplate(array $ids)
  {
    /** @var \Contao\FrontendTemplate|\stdClass $objTemplate */
    $objTemplate = new \Contao\FrontendTemplate($this->productListTpl);
    $objTemplate->setData($this->arrData);
    $objTemplate->product_list = StockBookingHelper::generateProductListForPackagingSlips($ids);
    $objTemplate->count = count($ids);
    $objTemplate->dateFormat    = $GLOBALS['TL_CONFIG']['dateFormat'];
    $objTemplate->timeFormat    = $GLOBALS['TL_CONFIG']['timeFormat'];
    $objTemplate->datimFormat   = $GLOBALS['TL_CONFIG']['datimFormat'];

    return $this->fixTemplateOutput($objTemplate->parse());
  }

  /**
   * @param $strBUffer
   *
   * @return array|string|string[]|null
   * @throws \Exception
   */
  protected function fixTemplateOutput($strBUffer) {
    // Generate template and fix PDF issues, see Contao's ModuleArticle
    $strBuffer = \Controller::replaceInsertTags($strBUffer, false);
    $strBuffer = html_entity_decode($strBuffer, ENT_QUOTES, $GLOBALS['TL_CONFIG']['characterSet']);
    $strBuffer = \Controller::convertRelativeUrls($strBuffer, '', true);

    // Remove form elements and JavaScript links
    $arrSearch = array
    (
      '@<form.*</form>@Us',
      '@<a [^>]*href="[^"]*javascript:[^>]+>.*</a>@Us'
    );

    $strBuffer = preg_replace($arrSearch, '', $strBuffer);

    // URL decode image paths (see contao/core#6411)
    // Make image paths absolute
    $blnOverrideRoot = false;
    $strBuffer = preg_replace_callback('@(src=")([^"]+)(")@', function ($args) use (&$blnOverrideRoot) {
      if (preg_match('@^(http://|https://)@', $args[2])) {
        return $args[1] . $args[2] . $args[3];
      }

      $path = rawurldecode($args[2]);

      if (method_exists(File::class, 'createIfDeferred')) {
        (new File($path))->createIfDeferred();
      }

      $blnOverrideRoot = true;
      return $args[1] . TL_ROOT . '/' . $path . $args[3];
    }, $strBuffer);

    if ($blnOverrideRoot) {
      $_SERVER['DOCUMENT_ROOT'] = TL_ROOT;
    }

    // Handle line breaks in preformatted text
    $strBuffer = preg_replace_callback('@(<pre.*</pre>)@Us', 'nl2br_callback', $strBuffer);

    // Default PDF export using TCPDF
    $arrSearch = array
    (
      '@<span style="text-decoration: ?underline;?">(.*)</span>@Us',
      '@(<img[^>]+>)@',
      '@(<div[^>]+block[^>]+>)@',
      '@[\n\r\t]+@',
      '@<br( /)?><div class="mod_article@',
      '@href="([^"]+)(pdf=[0-9]*(&|&amp;)?)([^"]*)"@'
    );

    $arrReplace = array
    (
      '<u>$1</u>',
      '<br>$1',
      '<br>$1',
      ' ',
      '<div class="mod_article',
      'href="$1$4"'
    );

    $strBuffer = preg_replace($arrSearch, $arrReplace, $strBuffer);
    return $strBuffer;
  }

}