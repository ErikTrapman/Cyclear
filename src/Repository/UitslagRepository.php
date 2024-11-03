<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Periode;
use App\Entity\Ploeg;
use App\Entity\Renner;
use App\Entity\Seizoen;
use App\Entity\Transfer;
use App\Entity\Uitslag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UitslagRepository extends ServiceEntityRepository
{
    public const CACHE_TAG = 'UitslagRepository';

    public function __construct(
        ManagerRegistry $registry,
        private readonly TagAwareCacheInterface $cache,
        private readonly SeizoenRepository $seizoenRepository,
        private readonly PloegRepository $ploegRepository,
        private readonly RennerRepository $rennerRepository,
        private readonly TransferRepository $transferRepository,
    ) {
        parent::__construct($registry, Uitslag::class);
    }

    public static function puntenSort(array &$values, $fallBackSort = 'afkorting', $sortKey = 'punten'): void
    {
        uasort($values, function ($a, $b) use ($fallBackSort, $sortKey) {
            if ($a instanceof Ploeg && $b instanceof Ploeg) {
                $aPoints = $a->getPunten();
                $bPoints = $b->getPunten();
            } else {
                $aPoints = $a[$sortKey];
                $bPoints = $b[$sortKey];
            }
            if ($aPoints == $bPoints) {
                if ($a instanceof Ploeg && $b instanceof Ploeg) {
                    $accessor = PropertyAccess::createPropertyAccessor();
                    return $accessor->getValue($a, $fallBackSort) < $accessor->getValue($b, $fallBackSort) ? -1 : 1;
                } else {
                    return $a[$fallBackSort] < $b[$fallBackSort] ? -1 : 1;
                }
            }
            return ($aPoints < $bPoints) ? 1 : -1;
        });
    }

    public function getPuntenByPloeg($seizoen = null, $ploeg = null, ?\DateTime $maxDate = null)
    {
        $key = __FUNCTION__ . $seizoen?->getId() . $ploeg?->getId() . $maxDate?->format('YmdHis');
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }
        $seizoen = $this->resolveSeizoen($seizoen);
        $params = ['seizoen' => $seizoen];
        $subQuery = $this->createQueryBuilder('u')
            ->select('ifnull(sum(u.ploegPunten),0)')
            ->innerJoin('u.wedstrijd', 'w')->where('w.seizoen = :seizoen')
            ->andWhere('u.ploeg = p')
            ->setParameter('seizoen', $seizoen);
        if (null !== $maxDate) {
            $subQuery->andWhere('w.datum < :maxdate');
            $maxDate->setTime(0, 0, 0);
            // $subQuery->setParameter('maxdate', $maxDate);
            $params['maxdate'] = $maxDate;
        }

        $qb = $this->ploegRepository->createQueryBuilder('p');
        $qb->addSelect('(' . $subQuery->getDQL() . ') AS punten');
        $qb->where('p.seizoen = :seizoen');
        if (null !== $ploeg) {
            $qb->andWhere('p = :ploeg');
            $params['ploeg'] = $ploeg;
        }
        $qb->orderBy('punten', 'DESC, p.afkorting ASC');
        $qb->setParameters(Util::buildParameters($params));
        $value = $qb->getQuery()->getResult();
        $item->set($value);
        $item->tag(self::CACHE_TAG);
        $this->cache->save($item);
        return $value;
    }

    public function getPuntenByPloegForPeriode(Periode $periode, ?Seizoen $seizoen = null): array
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $start = clone $periode->getStart();
        $start->setTime(0, 0, 0);
        $end = clone $periode->getEind();
        $end->setTime(23, 59, 59);
        $qb = $this->ploegRepository->createQueryBuilder('p');
        $subQ = $this->createQueryBuilder('u')
            ->innerJoin('u.wedstrijd', 'w')
            ->where($qb->expr()->between('w.datum', ':start', ':end'))->andWhere('u.ploeg = p')
            ->select('IFNULL(SUM(u.ploegPunten),0)');
        $qb->select('p')
            ->where('p.seizoen = :seizoen')
            ->addSelect(sprintf('(%s) AS punten', $subQ->getDQL()))
            ->groupBy('p')
            ->orderBy('punten', 'DESC')
            ->addOrderBy('p.afkorting', 'ASC');
        $qb->setParameters(Util::buildParameters(['start' => $start, 'end' => $end, 'seizoen' => $seizoen]));
        // flatten the result
        return array_map(function ($row) {
            return array_merge($row[0], ['punten' => $row['punten']]);
        }, $qb->getQuery()->getArrayResult());
    }

    public function getCountForPosition($seizoen = null, $pos = 1, ?\DateTime $start = null, ?\DateTime $end = null)
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $key = __FUNCTION__ . $seizoen->getId() . $pos . $start?->format('YmdHis') . $end?->format('YmdHis');
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }
        $parameters = ['seizoen' => $seizoen, 'pos' => $pos];
        $qb2 = $this->createQueryBuilder('u')
            ->select('SUM(IF(u.positie = :pos,1,0))')
            ->join('u.wedstrijd', 'w')
            ->where('u.ploeg = p.id')
            ->andWhere('w.seizoen = :seizoen')
            ->andWhere('u.ploegPunten > 0');
        if ($start && $end) {
            $start = clone $start;
            $start->setTime(0, 0, 0);
            $end = clone $end;
            $end->setTime(23, 59, 59);
            $qb2->andWhere('w.datum >= :start AND w.datum <= :end');
            $parameters['start'] = $start;
            $parameters['end'] = $end;
        }
        $qb = $this->ploegRepository->createQueryBuilder('p');
        $qb
            ->where('p.seizoen = :seizoen')
            ->addSelect(sprintf('IFNULL((%s),0) as freqByPos', $qb2->getDql()))
            ->groupBy('p.id')
            ->orderBy('freqByPos DESC, p.afkorting', 'ASC')
            ->setParameters(Util::buildParameters($parameters));
        $ret = $qb->getQuery()->getResult();
        $item->set($ret);
        $item->tag(self::CACHE_TAG);
        $this->cache->save($item);
        return $ret;
    }

    /**
     * @param Renner $renner
     * @param Seizoen|null $seizoen
     * @param bool $excludeZeros
     * @return array
     */
    public function getPuntenForRenner($renner, $seizoen = null, $excludeZeros = false)
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $qb = $this->getPuntenForRennerQb();
        $qb->andWhere('w.seizoen = :seizoen');
        if ($excludeZeros) {
            $qb->andWhere('u.rennerPunten > 0');
        }
        $qb->setParameters(Util::buildParameters(['seizoen' => $seizoen, 'renner' => $renner]));
        return $qb->getQuery()->getResult();
    }

    public function getTotalPuntenForRenner(Renner $renner, ?Seizoen $seizoen = null)
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $qb = $this->getPuntenForRennerQb();
        $qb->andWhere('w.seizoen = :seizoen');
        $qb->setParameters(Util::buildParameters(['seizoen' => $seizoen, 'renner' => $renner]));
        $qb->add('select', 'SUM(u.rennerPunten)');
        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getPuntenForRennerWithPloeg(Renner $renner, Ploeg $ploeg, $seizoen = null)
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $key = __FUNCTION__ . $renner->getId() . $ploeg->getId() . $seizoen->getId();
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }
        $qb = $this->getPuntenForRennerQb();
        $qb->andWhere('w.seizoen = :seizoen')->andWhere('u.ploeg = :ploeg');
        $qb->setParameters(Util::buildParameters(['seizoen' => $seizoen, 'ploeg' => $ploeg, 'renner' => $renner]));
        $qb->add('select', 'SUM(u.ploegPunten)');
        $res = $qb->getQuery()->getSingleScalarResult();
        $item->set($res);
        $item->tag(self::CACHE_TAG);
        $this->cache->save($item);
        return $res;
    }

    private function getPuntenForRennerQb(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->join('u.wedstrijd', 'w')
            ->where('u.renner = :renner')
            ->orderBy('u.id', 'DESC');
        return $qb;
    }

    public function getPuntenByPloegForDraftTransfers(Seizoen $seizoen, ?Ploeg $ploeg = null): array
    {
        $item = $this->cache->getItem('getPuntenByPloegForDraftTransfers' . $seizoen->getId() . $ploeg?->getId());
        if ($item->isHit()) {
            return $item->get();
        }
        $subQ = $this->rennerRepository->createQueryBuilder('r');
        $subQ->innerJoin('App\Entity\Transfer', 't', 'WITH',
            't.renner = r AND t.transferType = :draft AND t.seizoen = :seizoen')->andWhere('t.ploegNaar = :p');
        $subQ->select('DISTINCT r.id');
        $subQPoints = $this->createQueryBuilder('u');
        $subQPoints->select('IFNULL(SUM(u.rennerPunten),0)')
            ->groupBy('u.renner')
            ->innerJoin('u.wedstrijd', 'w')
            ->where('w.seizoen = :seizoen')
            ->andWhere($subQ->expr()->in('u.renner', $subQ->getDQL()));
        $subQPoints->setParameter('draft', Transfer::DRAFTTRANSFER)->setParameter('seizoen', $seizoen);
        if ($ploeg) {
            $retPloeg = $this->ploegRepository->createQueryBuilder('p')->where($subQ->expr()->eq('p', $ploeg->getId()))->getQuery()->getArrayResult();
            $subRes = $subQPoints->setParameter('p', $ploeg)->getQuery()->getScalarResult();
            $retPloeg['punten'] = array_sum(array_map(function ($item) {
                return (int)reset($item);
            }, $subRes));
            return [$retPloeg];
        }
        $res = [];
        $maxPointsPerRider = null !== $seizoen->getMaxPointsPerRider() ? $seizoen->getMaxPointsPerRider() : pow(8, 8);
        foreach ($this->ploegRepository
                     ->createQueryBuilder('p')->where('p.seizoen = :seizoen')
                     ->setParameter('seizoen', $seizoen)->getQuery()->getArrayResult() as $ploeg) {
            $subQPoints->setParameter('p', $ploeg['id']);
            $subRes = $subQPoints->getQuery()->getArrayResult();
            // results are grouped by rider. all riders can score the max amounts of maxPointsPerRider.
            $ploeg['punten'] = array_sum(array_map(function ($item) use ($maxPointsPerRider) {
                return (int)min($maxPointsPerRider, reset($item));
            }, $subRes));
            $res[] = $ploeg;
        }
        static::puntenSort($res, 'afkorting');
        $item->set($res);
        $item->tag(self::CACHE_TAG);
        $this->cache->save($item);
        return $item->get();
    }

    public function getPuntenByPloegForUserTransfersWithoutLoss(Seizoen $seizoen, ?\DateTime $start = null, ?\DateTime $end = null)
    {
        $key = 'getPuntenByPloegForUserTransfersWithoutLoss_' . $seizoen->getId() . $start?->format('Ymd') . $end?->format('Ymd');
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }
        $params = [':seizoen_id' => $seizoen->getId(), 'transfertype_draft' => Transfer::DRAFTTRANSFER];
        $startEndWhere = null;
        if ($start && $end) {
            $startEndWhere = ' AND (w.datum >= :start AND w.datum <= :end)';
            $start = clone $start;
            $start->setTime(0, 0, 0);
            $end = clone $end;
            $end->setTime(0, 0, 0);
            $params['start'] = $start->format('Y-m-d H:i:s');
            $params['end'] = $end->format('Y-m-d H:i:s');
        }
        // TODO DQL'en net als getCountForPosition
        $transfers = 'SELECT DISTINCT t.renner_id FROM transfer t
            WHERE t.transferType != ' . Transfer::DRAFTTRANSFER . ' AND t.ploegNaar_id = p.id AND t.seizoen_id = :seizoen_id
                AND t.renner_id NOT IN
                (SELECT t.renner_id FROM transfer t
                WHERE t.transferType = ' . Transfer::DRAFTTRANSFER . ' AND t.ploegNaar_id = p.id
                AND t.seizoen_id = :seizoen_id)';

        $sql = sprintf('
                SELECT p.id AS id, p.naam AS naam, p.afkorting AS afkorting, 100 AS b,
                (

                (SELECT IFNULL(SUM(u.ploegPunten),0)
                FROM uitslag u
                INNER JOIN wedstrijd w ON u.wedstrijd_id = w.id
                WHERE w.seizoen_id = :seizoen_id %s AND u.ploeg_id = p.id AND u.renner_id IN (%s))

                ) AS punten

                FROM ploeg p WHERE p.seizoen_id = :seizoen_id
                ORDER BY punten DESC, p.afkorting ASC
                ', $startEndWhere, $transfers);
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $res = $stmt->executeQuery($params)->fetchAllAssociative();
        $item->set($res);
        $item->tag(self::CACHE_TAG);
        $this->cache->save($item);
        return $item->get();
    }

    public function getLostDraftPuntenByPloeg($seizoen = null, ?\DateTime $start = null, ?\DateTime $end = null)
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $key = __FUNCTION__ . $seizoen->getId() . $start?->format('YmdHis') . $end?->format('YmdHis');
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }
        $res = [];
        $maxPointsPerRider = null !== $seizoen->getMaxPointsPerRider() ? $seizoen->getMaxPointsPerRider() : pow(8, 8);
        foreach ($this->ploegRepository
                     ->createQueryBuilder('p')->where('p.seizoen = :seizoen')
                     ->setParameter('seizoen', $seizoen)->getQuery()->getResult() as $ploeg) {
            $ploegDraftRenners = [];
            foreach ($this->ploegRepository->getDraftRennersWithPunten($ploeg) as $draftRenner) {
                $ploegDraftRenners[$draftRenner[0]->getId()] = (int)$draftRenner['punten'];
            }

            $teamResults = $this->getUitslagenForPloegForLostDraftsQb($ploeg, $seizoen, $start, $end);
            $teamPointsPerRider = [];
            /** @var Uitslag $teamResult */
            foreach ($teamResults->getQuery()->getResult() as $teamResult) {
                $riderId = $teamResult->getRenner()->getId();
                if (!array_key_exists($riderId, $teamPointsPerRider)) {
                    $teamPointsPerRider[$riderId] = 0;
                }
                // If a drafted rider has > maxPoints we do not calculate the points as "lost"
                // This rider has a valid reason to be transferred and should not be taken into account.
                // This might not be a waterproof solution because a rider can be transferred early and get maxPoints at another team.
                if (array_key_exists($riderId, $ploegDraftRenners) && $ploegDraftRenners[$riderId] >= $maxPointsPerRider) {
                    continue;
                }
                $teamPointsPerRider[$riderId] += $teamResult->getRennerPunten();
            }
            $ploeg->setPunten(array_sum($teamPointsPerRider));
            $res[] = $ploeg;
        }
        static::puntenSort($res);
        $item->set($res);
        $item->tag(self::CACHE_TAG);
        $this->cache->save($item);
        return $res;
    }

    public function getPuntenByPloegForUserTransfers($seizoen = null): array
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $key = __FUNCTION__ . $seizoen->getId();
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }
        $this->transferRepository->generateTempTableWithDraftRiders($seizoen);

        // TODO DQL'en net als getCountForPosition
        $transfers = 'SELECT DISTINCT t.renner_id FROM transfer t
            WHERE t.transferType != ' . Transfer::DRAFTTRANSFER . ' AND t.ploegNaar_id = p.id AND t.seizoen_id = :seizoen_id
                AND t.renner_id NOT IN ( SELECT t.renner_id FROM transfer t WHERE t.transferType = ' . Transfer::DRAFTTRANSFER . ' AND t.ploegNaar_id = p.id AND t.seizoen_id = :seizoen_id )';
        $sql = sprintf('SELECT p.id AS id, p.naam AS naam, p.afkorting AS afkorting, 400 as d,
                ((SELECT IFNULL(SUM(u.ploegPunten),0)
                FROM uitslag u
                INNER JOIN wedstrijd w ON u.wedstrijd_id = w.id
                WHERE w.seizoen_id = :seizoen_id AND u.ploeg_id = p.id AND u.renner_id IN (%s))

                ) AS punten

                FROM ploeg p WHERE p.seizoen_id = :seizoen_id
                ORDER BY punten DESC, p.afkorting ASC
                ', $transfers);
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $ret = $stmt->executeQuery([':seizoen_id' => $seizoen->getId(), 'transfertype_draft' => Transfer::DRAFTTRANSFER])->fetchAllAssociative();
        $item->set($ret);
        $item->tag(self::CACHE_TAG);
        $this->cache->save($item);
        return $ret;
    }

    public function getUitslagenForPloegForNonDraftTransfersQb(Ploeg $ploeg, $seizoen = null, ?\DateTime $start = null, ?\DateTime $end = null)
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $parameters = ['ploeg' => $ploeg, 'seizoen' => $seizoen];
        $transfers = $this->transferRepository->getTransferredInNonDraftRenners($ploeg, $seizoen);

        $qb = $this->createQueryBuilder('u');
        $qb->where('u.ploeg = :ploeg')
            ->join('u.wedstrijd', 'w')
            ->join('u.renner', 'renner')->addSelect('renner')
            ->andWhere('w.seizoen = :seizoen')
            ->andWhere($qb->expr()->in('u.renner', array_merge(array_unique(array_map(function ($a) {
                return $a->getRenner()->getId();
            }, $transfers)), [0])))
            ->andWhere('u.ploegPunten > 0')
            ->setParameters(Util::buildParameters($parameters))
            ->orderBy('w.datum DESC, u.id', 'DESC');
        return $qb;
    }

    /**
     * @param null $seizoen
     * @param mixed $ploeg
     * @return QueryBuilder
     */
    public function getUitslagenForPloegForLostDraftsQb($ploeg, $seizoen = null, ?\DateTime $start = null, ?\DateTime $end = null)
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $parameters = ['ploeg' => $ploeg, 'seizoen' => $seizoen];
        $draftrenners = $this->ploegRepository->getDraftRenners($ploeg);
        $qb = $this->createQueryBuilder('u');
        $qb
            // ->where('u.ploeg = :ploeg')
            ->join('u.wedstrijd', 'w')
            ->andWhere('w.seizoen = :seizoen')
            ->andWhere($qb->expr()->in('u.renner', array_merge(array_unique(array_map(function ($r) {
                return $r->getId();
            }, $draftrenners)), [0])))
            ->andWhere('u.rennerPunten > 0')
            // ->andWhere('1=1')
            ->andWhere('(u.ploeg != :ploeg OR u.ploeg IS NULL) OR (u.ploeg = :ploeg AND u.ploegPunten = 0)')
            ->setParameters(Util::buildParameters($parameters))
            ->orderBy('w.datum DESC, u.id', 'DESC');
        if ($start && $end) {
            $startEndWhere = '(w.datum >= :start AND w.datum <= :end)';
            $start = clone $start;
            $start->setTime(0, 0, 0);
            $end = clone $end;
            $end->setTime(0, 0, 0);
            $qb->andWhere($startEndWhere)->setParameter('start', $start)->setParameter('end', $end);
        }
        return $qb;
    }

    public function getUitslagenForPloegQb($ploeg, $seizoen = null): QueryBuilder
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $parameters = ['ploeg' => $ploeg, 'seizoen' => $seizoen];
        return $this->createQueryBuilder('u')
            ->join('u.wedstrijd', 'w')
            ->where('u.ploeg = :ploeg')
            ->andWhere('w.seizoen = :seizoen')
            ->andWhere('u.ploegPunten > 0')
            ->setParameters(Util::buildParameters($parameters))
            ->orderBy('w.datum DESC, u.id', 'DESC');
    }

    public function getUitslagenForPloegByPositionQb($ploeg, $position, $seizoen = null): QueryBuilder
    {
        $seizoen = $this->resolveSeizoen($seizoen);
        $parameters = ['ploeg' => $ploeg, 'seizoen' => $seizoen, 'position' => $position];
        return $this->createQueryBuilder('u')
            ->join('u.wedstrijd', 'w')
            ->where('u.ploeg = :ploeg')
            ->andWhere('w.seizoen = :seizoen')
            ->andWhere('u.ploegPunten > 0')
            ->andWhere('u.positie = :position')
            ->setParameters(Util::buildParameters($parameters))
            ->orderBy('w.datum DESC, u.id', 'DESC');
    }

    public function getBestTransfers(Seizoen $seizoen, ?\DateTime $start = null, ?\DateTime $end = null)
    {
        $key = __FUNCTION__ . $seizoen->getId() . $start?->format('Ymd') . $end?->format('Ymd');
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }
        $res = [];
        $ploegen = $this->ploegRepository->findBy(['seizoen' => $seizoen]);
        foreach ($ploegen as $ploeg) {
            foreach ($this->getUitslagenForPloegForNonDraftTransfersQb($ploeg, $seizoen)
                         ->getQuery()->getResult() as $transferResult) {
                $index = $transferResult->getRenner() . $ploeg->getAfkorting();
                if (!array_key_exists($index, $res)) {
                    $res[$index] = [
                        'rider' => $transferResult->getRenner(),
                        'team' => $transferResult->getPloeg(),
                        'points' => 0,
                    ];
                }
                $res[$index]['points'] += $transferResult->getPloegPunten();
            }
        }
        uasort($res, function ($a, $b) {
            return $a['points'] > $b['points'] ? -1 : 1;
        });
        $item->set($res);
        $item->tag(self::CACHE_TAG);
        $this->cache->save($item);
        return $res;
    }

    private function resolveSeizoen(?Seizoen $seizoen = null): Seizoen
    {
        if (null === $seizoen) {
            $seizoen = $this->seizoenRepository->getCurrent();
        }
        return $seizoen;
    }
}
