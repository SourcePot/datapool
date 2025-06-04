<?php
/*
* This file is part of the Datapool CMS package.
*
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Tools;

use horstoeko\zugferd\ZugferdDocumentPdfReader;
use horstoeko\zugferd\ZugferdDocumentReader;

class ZUGFeRD{

    private $oc;

    public function __construct(array $oc)
    {    
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function xmlString2entry(string $string, array $entry):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        $document = ZugferdDocumentReader::readAndGuessFromContent($string);
        if (empty($document)){
            $this->oc['logger']->log('Info','Method "{class} &rarr; {function}()" failed to create a document object from string.',$context);
            return $entry;
        }
        $entry=$this->document2entry($document,$entry);
        return $entry;
    }

    public function file2entry(string $file, array $entry):array
    {
        $pathinfo=pathinfo($file);
        if (!is_file($file)){
            // nothing to do
        } else if ($pathinfo['extension']==='pdf'){
            $entry=$this->pdf2entry($file,$entry,$pathinfo);
        } else if ($pathinfo['extension']==='xml'){
            $entry=$this->xml2entry($file,$entry,$pathinfo);
        }
        return $entry;
    }

    private function pdf2entry(string $file, array $entry, array $pathinfo):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'file'=>$pathinfo['basename']];
        try{
            $document = ZugferdDocumentPdfReader::readAndGuessFromFile($file);
            if (empty($document)){
                $this->oc['logger']->log('Info','Method "{class} &rarr; {function}()" failed to create a document object from "{file}".',$context);
            } else {
                $entry=$this->document2entry($document,$entry);
            }
        } catch (\Exception $e){
            $context['msg']=$e->getMessage();
            $this->oc['logger']->log('Info','Method "{class} &rarr; {function}()" failed with "{msg}" for "{file}".',$context);
        }
        return $entry;
    }
    
    private function xml2entry(string $file, array $entry, array $pathinfo):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'file'=>$pathinfo['basename']];
        $document = ZugferdDocumentReader::readAndGuessFromFile($file);
        if (empty($document)){
            $this->oc['logger']->log('Info','Method "{class} &rarr; {function}()" failed to create a document object from "{file}".',$context);
            return $entry;
        }
        $entry=$this->document2entry($document,$entry);
        return $entry;
    }

    private function document2entry($document, array $entry):array
    {
        $entry['Content']['zugferd']=[];
        $document->getDocumentInformation($entry['Content']['zugferd']['documentno'],$entry['Content']['zugferd']['documenttypecode'],$entry['Content']['zugferd']['documentdate'],$entry['Content']['zugferd']['invoiceCurrency'],$entry['Content']['zugferd']['taxCurrency'],$entry['Content']['zugferd']['documentname'],$entry['Content']['zugferd']['documentlanguage'],$entry['Content']['zugferd']['effectiveSpecifiedPeriod']);
        // loop through document positions
        $docIndex=0;
        if ($document->firstDocumentPosition()){
            do {
                $docIndex++;
                $document->getDocumentPositionGenerals(
                    $entry['Content']['zugferd']['positions'][$docIndex]['lineid'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['linestatuscode'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['linestatusreasoncode']
                );
                $document->getDocumentPositionProductDetails(
                    $entry['Content']['zugferd']['positions'][$docIndex]['prodname'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['proddesc'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['prodsellerid'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['prodbuyerid'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['prodglobalidtype'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['prodglobalid']
                );
                $document->getDocumentPositionGrossPrice(
                    $entry['Content']['zugferd']['positions'][$docIndex]['grosspriceamount'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['grosspricebasisquantity'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['grosspricebasisquantityunitcode']
                );
                $document->getDocumentPositionNetPrice(
                    $entry['Content']['zugferd']['positions'][$docIndex]['netpriceamount'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['netpricebasisquantity'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['netpricebasisquantityunitcode']
                );
                $document->getDocumentPositionLineSummation(
                    $entry['Content']['zugferd']['positions'][$docIndex]['lineTotalAmount'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['totalAllowanceChargeAmount']
                );
                $document->getDocumentPositionQuantity(
                    $entry['Content']['zugferd']['positions'][$docIndex]['billedquantity'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['billedquantityunitcode'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['chargeFreeQuantity'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['chargeFreeQuantityunitcode'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['packageQuantity'],
                    $entry['Content']['zugferd']['positions'][$docIndex]['packageQuantityunitcode']
                );
                $taxIndex=0;
                if ($document->firstDocumentPositionTax()) {
                    do {
                        $taxIndex++;
                        $document->getDocumentPositionTax(
                            $entry['Content']['zugferd']['positions'][$docIndex]['taxes'][$taxIndex]['categoryCode'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['taxes'][$taxIndex]['typeCode'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['taxes'][$taxIndex]['rateApplicablePercent'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['taxes'][$taxIndex]['calculatedAmount'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['taxes'][$taxIndex]['exemptionReason'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['taxes'][$taxIndex]['exemptionReasonCode']
                        );
                    } while ($document->nextDocumentPositionTax());
                }
                if ($document->firstDocumentPositionAllowanceCharge()){
                    $chargesIndex=0;
                    do {
                        $chargesIndex++;
                        $document->getDocumentPositionAllowanceCharge(
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['actualAmount'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['isCharge'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['calculationPercent'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['basisAmount'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['reason'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['taxTypeCode'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['taxCategoryCode'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['rateApplicablePercent'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['sequence'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['basisQuantity'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['basisQuantityUnitCode'],
                            $entry['Content']['zugferd']['positions'][$docIndex]['Allowances/Charges'][$chargesIndex]['reasonCode']
                        );
                    } while ($document->nextDocumentPositionAllowanceCharge());
                }
            } while ($document->nextDocumentPosition());
        }
        // loop through document allowance(s)/charge(s) 
        $docChargeIndex=0;
        if ($document->firstDocumentAllowanceCharge()){
            do {
                $docChargeIndex++;
                $document->getDocumentAllowanceCharge(
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['actualAmount'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['isCharge'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['taxCategoryCode'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['taxTypeCode'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['rateApplicablePercent'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['sequence'], 
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['calculationPercent'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['basisAmount'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['basisQuantity'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['basisQuantityUnitCode'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['reasonCode'],
                    $entry['Content']['zugferd']['allowanceCharge'][$docChargeIndex]['reason']
                );
            } while ($document->nextDocumentAllowanceCharge());
        }

        // loop through document tax 
        $docTaxIndex=0;
        if ($document->firstDocumentTax()){
            do {
                $docTaxIndex++;
                $document->getDocumentTax(
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['categoryCode'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['typeCode'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['basisAmount'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['calculatedAmount'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['rateApplicablePercent'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['exemptionReason'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['exemptionReasonCode'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['lineTotalBasisAmount'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['allowanceChargeBasisAmount'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['taxPointDate'],
                    $entry['Content']['zugferd']['tax'][$docTaxIndex]['dueDateTypeCode']
                );
            } while ($document->nextDocumentTax());
        }
        // summation
        $document->getDocumentSummation(
            $entry['Content']['zugferd']['grandTotalAmount'],
            $entry['Content']['zugferd']['duePayableAmount'],
            $entry['Content']['zugferd']['lineTotalAmount'],
            $entry['Content']['zugferd']['chargeTotalAmount'],
            $entry['Content']['zugferd']['allowanceTotalAmount'],
            $entry['Content']['zugferd']['taxBasisTotalAmount'],
            $entry['Content']['zugferd']['taxTotalAmount'],
            $entry['Content']['zugferd']['roundingAmount'],
            $entry['Content']['zugferd']['totalPrepaidAmount']
        );
        return $entry;
    }

}
?>