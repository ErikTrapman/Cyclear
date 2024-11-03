<?php declare(strict_types=1);

namespace App\Repository;

use App\CQRanking\Exception\CyclearGameBundleCQException;
use App\Entity\Seizoen;
use App\Entity\Wedstrijd;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WedstrijdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wedstrijd::class);
    }

    /**
     * Gets refstage for given $wedstrijd.
     * refStage is the first registered stage for a multiple-days race.
     * Typically, the Wedstrijd has a name like 'Dubai Tour, General classification'
     * We use that to lookup 'Dubai Tour, Stage 1' or 'Dubai Tour, Prologue'.
     */
    public function getRefStage(Wedstrijd $wedstrijd): ?Wedstrijd
    {
        $parts = explode(',', $wedstrijd->getNaam());
        if (empty($parts)) {
            throw new CyclearGameBundleCQException('Unable to lookup refStage for ' . $wedstrijd->getNaam());
        }
        $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', \Transliterator::FORWARD);
        $stage = $transliterator->transliterate($parts[0]);
        $prologue = $transliterator->transliterate($parts[0]);
        $stage1 = $stage . ', Stage 1%';
        $prologue = $prologue . ', Prologue%';
        $qb = $this->createQueryBuilder('w');
        $qb->where('w.seizoen = :seizoen')->andWhere(
            $qb->expr()->orX(
                $qb->expr()->like('w.naam', ':stage1'),
                $qb->expr()->like('w.naam', ':prol'))
        );
        $qb->setParameters(Util::buildParameters(['seizoen' => $wedstrijd->getSeizoen(), 'stage1' => $stage1, 'prol' => $prologue]));
        $res = $qb->getQuery()->getResult();
        if (0 === count($res)) {
            // try again with 'Stage 2' as we do not always register stage 1 if it's a TTT. It's the best we can get.
            $qb = $this->createQueryBuilder('w');
            $qb->where('w.seizoen = :seizoen')->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('w.naam', ':stage2'))
            );
            $qb->setParameters(Util::buildParameters(['seizoen' => $wedstrijd->getSeizoen(), 'stage2' => sprintf('%s, Stage 2%%', $stage)]));
            $res = $qb->getQuery()->getResult();
            if (0 === count($res)) {
                throw new CyclearGameBundleCQException('Unable to lookup refStage for ' . $wedstrijd->getNaam() . '. Have ' . count($res) . ' results');
            }
            return $res[0];
        }
        return $res[0];
    }

    /**
     * Gets all stages for given $wedstrijd.
     * Typically the Wedstrijd has a name like 'Dubai Tour, General classification'
     * We use that to lookup 'Dubai Tour, Stage *' or 'Dubai Tour, Prologue'.
     *
     * @return Wedstrijd[]
     */
    public function getRefStages(Wedstrijd $wedstrijd)
    {
        $parts = explode(',', $wedstrijd->getNaam());
        if (empty($parts)) {
            throw new CyclearGameBundleCQException('Unable to lookup refStage for ' . $wedstrijd->getNaam());
        }
        $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', \Transliterator::FORWARD);
        $stage = $transliterator->transliterate($parts[0]);
        $prologue = $transliterator->transliterate($parts[0]);
        $stages = $stage . ', Stage%';
        $prologue = $prologue . ', Prologue%';
        $qb = $this->createQueryBuilder('w');
        $qb->where('w.seizoen = :seizoen')->andWhere(
            $qb->expr()->orX(
                $qb->expr()->like('w.naam', ':stages'),
                $qb->expr()->like('w.naam', ':prol'))
        );
        $qb->setParameters(Util::buildParameters(['seizoen' => $wedstrijd->getSeizoen(), 'stages' => $stages, 'prol' => $prologue]));
        return $qb->getQuery()->getResult();
    }

    public function getLatest(Seizoen $seizoen, int $limit = 20): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.seizoen = :seizoen')
            ->setParameter('seizoen', $seizoen)
            ->orderBy('w.datum DESC, w.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
