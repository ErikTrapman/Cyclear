<?php

/*
 * This file is part of the Cyclear-game package.
 *
 * (c) Erik Trapman <veggatron@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cyclear\GameBundle\Entity;

use Doctrine\ORM\EntityRepository;

class PloegRepository extends EntityRepository
{

    public function getRenners($ploeg)
    {
        $renners = array();
        foreach ($this->_em->getRepository("CyclearGameBundle:Contract")
                     ->createQueryBuilder('c')
                     ->where('c.ploeg = :ploeg')
                     ->andWhere('c.seizoen = :seizoen')
                     ->andWhere('c.eind IS NULL')
                     ->setParameters(array('ploeg' => $ploeg, 'seizoen' => $ploeg->getSeizoen()))
                     ->orderBy('c.id', 'ASC')
                     ->getQuery()->getResult() as $contract) {

            $renners[] = $contract->getRenner();
        }
        return $renners;
    }

    public function getDraftRenners(Ploeg $ploeg)
    {
        return $this->_em->getRepository("CyclearGameBundle:Renner")
            ->createQueryBuilder("r")
            ->innerJoin("CyclearGameBundle:Transfer", "t", 'WITH', 't.renner = r')
            ->where("t.transferType = " . Transfer::DRAFTTRANSFER)
            ->andWhere("t.ploegNaar = :ploeg")
            ->andWhere("t.seizoen = :seizoen")
            ->setParameters(array("ploeg" => $ploeg, "seizoen" => $ploeg->getSeizoen()))->getQuery()->getResult();
    }

    public function getRennersWithPunten(Ploeg $ploeg)
    {
        $renners = $this->getRenners($ploeg);
        $ret = array();
        $uitslagRepo = $this->_em->getRepository("CyclearGameBundle:Uitslag");
        foreach ($renners as $index => $renner) {
            $punten = $uitslagRepo->getPuntenForRennerWithPloeg($renner, $ploeg, $ploeg->getSeizoen());
            $ret[] = array(0 => $renner, 'punten' => (int)$punten, 'index' => $index);
        }
        $this->puntenSort($ret);
        return $ret;
    }

    public function getDraftRennersWithPunten(Ploeg $ploeg, $sort = true)
    {
        $ret = array();
        $renners = $this->getDraftRenners($ploeg);
        $uitslagRepo = $this->_em->getRepository("CyclearGameBundle:Uitslag");
        foreach ($renners as $index => $renner) {
            $ret[] = array(0 => $renner, 'punten' => $uitslagRepo->getTotalPuntenForRenner($renner, $ploeg->getSeizoen()), 'index' => $index);
        }
        if ($sort) {
            $this->puntenSort($ret);
        }
        return $ret;
    }

    private function puntenSort(&$values)
    {
        uasort($values, function ($a, $b) {
            if ($a['punten'] == $b['punten']) {
                return $a['index'] < $b['index'] ? -1 : 1;
            }
            return ($a['punten'] < $b['punten']) ? 1 : -1;
        });
    }
}