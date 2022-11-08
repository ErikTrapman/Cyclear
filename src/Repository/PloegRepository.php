<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\Ploeg;
use App\Entity\Renner;
use App\Entity\Transfer;
use App\Entity\Uitslag;
use Doctrine\ORM\EntityRepository;

class PloegRepository extends EntityRepository
{
    /**
     * @psalm-return list<mixed>
     */
    public function getRenners(Ploeg $ploeg): array
    {
        $renners = [];
        foreach ($this->_em->getRepository(Contract::class)
                     ->createQueryBuilder('c')
                     ->where('c.ploeg = :ploeg')
                     ->andWhere('c.seizoen = :seizoen')
                     ->andWhere('c.eind IS NULL')
                     ->setParameters(['ploeg' => $ploeg, 'seizoen' => $ploeg->getSeizoen()])
                     ->orderBy('c.id', 'ASC')
                     ->getQuery()->getResult() as $contract) {
            $renners[] = $contract->getRenner();
        }
        return $renners;
    }

    public function getDraftRenners(Ploeg $ploeg)
    {
        return $this->_em->getRepository(Renner::class)
            ->createQueryBuilder('r')
            ->innerJoin("App\Entity\Transfer", 't', 'WITH', 't.renner = r')
            ->where('t.transferType = ' . Transfer::DRAFTTRANSFER)
            ->andWhere('t.ploegNaar = :ploeg')
            ->andWhere('t.seizoen = :seizoen')
            ->setParameters(['ploeg' => $ploeg, 'seizoen' => $ploeg->getSeizoen()])->getQuery()->getResult();
    }

    public function getRennersWithPunten(Ploeg $ploeg)
    {
        $renners = $this->getRenners($ploeg);
        $ret = [];
        $uitslagRepo = $this->_em->getRepository(Uitslag::class);
        foreach ($renners as $index => $renner) {
            $punten = $uitslagRepo->getPuntenForRennerWithPloeg($renner, $ploeg, $ploeg->getSeizoen());
            $ret[] = [0 => $renner, 'punten' => (int)$punten, 'index' => $index];
        }
        $this->puntenSort($ret);
        return $ret;
    }

    public function getDraftRennersWithPunten(Ploeg $ploeg, $sort = true)
    {
        $ret = [];
        $seizoen = $ploeg->getSeizoen();
        $renners = $this->getDraftRenners($ploeg);
        $uitslagRepo = $this->_em->getRepository(Uitslag::class);
        foreach ($renners as $index => $renner) {
            $rennerPunten = $uitslagRepo->getTotalPuntenForRenner($renner, $ploeg->getSeizoen());
            $punten = $rennerPunten;
            if (null !== $seizoen->getMaxPointsPerRider()) {
                $punten = min($seizoen->getMaxPointsPerRider(), $punten);
            }

            $ret[] = [
                0 => $renner,
                'punten' => $punten,
                'rennerPunten' => $rennerPunten,
                'index' => $index,];
        }
        if ($sort) {
            $this->puntenSort($ret);
        }
        return $ret;
    }

    private function puntenSort(array &$values): void
    {
        uasort($values, function ($a, $b) {
            if ($a['punten'] == $b['punten']) {
                return $a['index'] < $b['index'] ? -1 : 1;
            }
            return ($a['punten'] < $b['punten']) ? 1 : -1;
        });
    }
}
