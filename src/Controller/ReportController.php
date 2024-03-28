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

namespace Krabo\IsotopePackagingSlipBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\Database;
use Contao\System;
use Krabo\IsotopePackagingSlipBundle\Helper\PackagingSlipCheckAvailability;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopeStockBundle\Helper\BookingHelper;
use Krabo\IsotopeStockBundle\Model\BookingModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController {

  /**
   * @var \Twig\Environment
   */
  private $twig;

  /**
   * @var ContaoCsrfTokenManager
   */
  private $tokenManager;

  /**
   * @var string
   */
  private $csrfTokenName;

  public function __construct(TwigEnvironment $twig, ContaoCsrfTokenManager $tokenManager) {
    $this->twig = $twig;
    $this->tokenManager = $tokenManager;
    $this->csrfTokenName = System::getContainer()
      ->getParameter('contao.csrf_token_name');
  }

    /**
     * @Route("/contao/tl_isotope_packaging_slip/check_availability",
     *     name="tl_isotope_packaging_slip_check_availability",
     *     defaults={"_scope": "backend", "_token_check": true}
     * )
     */
  public function checkAvailability(Request $request) {
      PackagingSlipCheckAvailability::checkProductAvailability();
      PackagingSlipCheckAvailability::checkPackagingSlips();
      PackagingSlipCheckAvailability::checkForPaidOrders();
      return new Response('Done');
  }

  /**
   * @Route("/contao/tl_isotope_packaging_slip/report",
   *     name="tl_isotope_packaging_slip_report",
   *     defaults={"_scope": "backend", "_token_check": true}
   * )
   */
  public function packagingSlipReport(Request $request): Response
  {
    \Contao\System::loadLanguageFile(IsotopePackagingSlipModel::getTable());
    $defaultData = [];
    $formBuilder = $this->createFormBuilder($defaultData);

    $formBuilder->add('scheduled_picking_date_start', DateType::class, [
      'label' => $GLOBALS['TL_LANG'][IsotopePackagingSlipModel::getTable()]['scheduled_picking_date_start'],
      'widget' => 'single_text',
      'input_format' => 'y-m-d',
      'html5' => true,
      'attr' => [
        'class' => 'tl_text',
      ],
      'row_attr' => [
        'class' => 'w50 widget'
      ]
    ]);

    $formBuilder->add('scheduled_picking_date_end', DateType::class, [
      'label' => $GLOBALS['TL_LANG'][IsotopePackagingSlipModel::getTable()]['scheduled_picking_date_end'],
      'widget' => 'single_text',
      'input_format' => 'y-m-d',
      'html5' => true,
      'attr' => [
        'class' => 'tl_text',
      ],
      'row_attr' => [
        'class' => 'w50 widget'
      ]
    ]);

    $formBuilder->add('save', SubmitType::class, [
      'label' => $GLOBALS['TL_LANG'][IsotopePackagingSlipModel::getTable()]['viewReport'][0],
      'attr' => [
        'class' => 'tl_submit',
      ]
    ]);
    $formBuilder->add('REQUEST_TOKEN', HiddenType::class, [
      'data' => $this->tokenManager->getToken($this->csrfTokenName)
    ]);

    $form = $formBuilder->getForm();
    $form->handleRequest($request);
    $report = '';
    if ($form->isSubmitted() && $form->isValid()) {
      $data = $form->getData();
      $startDate = $data['scheduled_picking_date_start'];
      $startDate->setTime(0, 0);
      $endDate = $data['scheduled_picking_date_end'];
      $endDate->setTime(0, 0);
      $report = $this->generateReport($startDate->getTimestamp(), $endDate->getTimestamp());
    }

    $response = new Response();
    $templateData = [
      'title' => $GLOBALS['TL_LANG'][IsotopePackagingSlipModel::getTable()]['viewReport'][1],
      'form' => $form->createView(),
      'report' => $report,
    ];
    $content = $this->twig->render('@IsotopePackagingSlip/tl_isotope_packaging_slip_report.html.twig', $templateData);
    $response->setContent($content);
    return $response;
  }

  protected function generateReport($startTimeStamp, $endTimeStamp) {
    $today = new \DateTime();
    $today->setTime(0,0);
    $today = $today->getTimestamp();
    $includeEmpty = false;
    if ($today >= $startTimeStamp && $today <= $endTimeStamp) {
      $includeEmpty = true;
    }

    $timeStampWhere = "((`tl_isotope_packaging_slip`.`scheduled_picking_date` >= ? AND `tl_isotope_packaging_slip`.`scheduled_picking_date` <= ?)";
    if ($includeEmpty) {
      $timeStampWhere .= " OR (`tl_isotope_packaging_slip`.`scheduled_picking_date` = '') OR (`tl_isotope_packaging_slip`.`scheduled_picking_date` IS NULL)";
    }
    $timeStampWhere .= ")";

    $sql = "SELECT
      `tl_isotope_packaging_slip`.`shipper_id`,
      `tl_isotope_packaging_slip_shipper`.`name` AS `shipper`,
      `tl_isotope_packaging_slip`.`id`,
      SUM(`tl_isotope_packaging_slip_product_collection`.`quantity`) AS `quantity`
      FROM `tl_isotope_packaging_slip`
      INNER JOIN `tl_isotope_packaging_slip_product_collection` ON `tl_isotope_packaging_slip_product_collection`.`pid` = `tl_isotope_packaging_slip`.`id`     
      LEFT JOIN `tl_isotope_packaging_slip_shipper` ON `tl_isotope_packaging_slip_shipper`.`id` = `tl_isotope_packaging_slip`.`shipper_id`
      WHERE (`tl_isotope_packaging_slip`.`status` = '0' OR `tl_isotope_packaging_slip`.`status` = '1') AND  
    ";
    $sql .= $timeStampWhere;
    $sql .= "GROUP BY `tl_isotope_packaging_slip`.`id`";
    $sql .= "ORDER BY `shipper`";
    $result = Database::getInstance()->prepare($sql)->execute($startTimeStamp, $endTimeStamp);
    $reportData = [];
    while($result->next()) {
      if (!isset($reportData[$result->shipper_id])) {
        $reportData[$result->shipper_id] = [
          'shipper' => $GLOBALS['TL_LANG'][IsotopePackagingSlipModel::getTable()]['shipper_id'][0]. ': '.($result->shipper ?? '-'),
          '1' => 0,
          '2' => 0,
          '3' => 0,
          '4' => 0,
          'total' => 0,
        ];
      }
      $reportData[$result->shipper_id]['total'] ++;
      if ($result->quantity == 1) {
        $reportData[$result->shipper_id]['1'] ++;
      } elseif ($result->quantity == 2) {
        $reportData[$result->shipper_id]['2'] ++;
      } elseif ($result->quantity == 3) {
        $reportData[$result->shipper_id]['3'] ++;
      } elseif ($result->quantity > 3) {
        $reportData[$result->shipper_id]['4'] ++;
      }
    }

    $templateData = [
      'reportData' => $reportData,
    ];
    return $this->twig->render('@IsotopePackagingSlip/tl_isotope_packaging_slip_report_data.html.twig', $templateData);
  }
}