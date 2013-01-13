<?php

namespace Cyclear\GameBundle\Calculator;

use Cyclear\GameBundle\Entity\Renner;

/**
 * Description of PuntenCalculator
 *
 * @author Erik
 */
class PuntenCalculator
{
    private $em;

    public function __construct($em)
    {
        $this->em = $em;
    }

    public function canGetPoints(Renner $renner, $wedstrijdDatum, $referentieDatum = null)
    {
        $transferRepo = $this->em->getRepository('CyclearGameBundle:Transfer');
        if (null !== $referentieDatum) {
            $transferFromRefDatum = $transferRepo->findLastTransferForDate($renner, $referentieDatum);
            if (!$transferFromRefDatum) {
                return false;
            }
            $transferFromWedstrijd = $transferRepo->findLastTransferForDate($renner, $wedstrijdDatum);
            // als de transfer vanaf de wedstrijd-datum niet dezelfde is als vanaf de ref-datum, dan is er sprake van 
            //  een of meerdere transfers gedurende deze periode. dan geen punten
            if ($transferFromWedstrijd !== $transferFromRefDatum) {
                return false;
            }
            return $this->validateTransfer($transferFromRefDatum, $wedstrijdDatum);
        } else {
            $lastTransfer = $transferRepo->findLastTransferForDate($renner, $wedstrijdDatum);
            if (!$lastTransfer) {
                return false;
            }
            return $this->validateTransfer($lastTransfer, $wedstrijdDatum);
        }
    }

    private function validateTransfer($transfer, $validationDatum)
    {
        $transferDatum = clone $transfer->getDatum();
        $transferDatum->setTime(0, 0, 0);
        $clonedDatum = clone $validationDatum;
        $clonedDatum->setTime(0, 0, 0);
        if ($transferDatum >= $clonedDatum) {
            return false;
        }
        return true;
    }
}