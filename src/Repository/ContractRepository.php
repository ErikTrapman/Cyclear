<?php declare(strict_types=1);

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Repository;

use App\Entity\Contract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    public function getCurrentContract($renner, $seizoen): ?Contract
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.renner = :renner')
            ->andWhere('c.seizoen = :seizoen')
            ->andWhere('c.eind IS NULL')
            ->orderBy('c.id', 'DESC');
        $qb->setParameters(Util::buildParameters(['renner' => $renner, 'seizoen' => $seizoen]));

        $res = $qb->getQuery()->getResult();
        if (empty($res)) {
            return null;
        }
        return $res[0];
    }

    public function getLastContract($renner, $seizoen, $ploeg = null)
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.renner = :renner')
            ->andWhere('c.seizoen = :seizoen');
        $qb->setParameters(Util::buildParameters(['renner' => $renner, 'seizoen' => $seizoen]));
        if (null !== $ploeg) {
            $qb->andWhere('c.ploeg = :ploeg');
            $qb->setParameter('ploeg', $ploeg);
        }
        $qb->orderBy('c.id', 'DESC')->setMaxResults(1);
        $res = $qb->getQuery()->getResult();
        if (empty($res)) {
            return null;
        }
        return $res[0];
    }

    public function getLastFinishedContract($renner, $seizoen, $ploeg = null)
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.renner = :renner')
            ->andWhere('c.seizoen = :seizoen')
            ->andWhere('c.eind IS NOT NULL');
        $qb->setParameters(Util::buildParameters(['renner' => $renner, 'seizoen' => $seizoen]));
        if (null !== $ploeg) {
            $qb->andWhere('c.ploeg = :ploeg');
            $qb->setParameter('ploeg', $ploeg);
        }
        $qb->orderBy('c.id', 'DESC')->setMaxResults(1);
        $res = $qb->getQuery()->getResult();
        if (empty($res)) {
            return null;
        }
        return $res[0];
    }
}
