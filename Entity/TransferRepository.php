<?php

namespace Cyclear\GameBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * RennerRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TransferRepository extends EntityRepository
{

    public function findByRenner(Renner $renner, $seizoen = null)
    {
        if (null === $seizoen) {
            $seizoen = $this->_em->getRepository("CyclearGameBundle:Seizoen")->getCurrent();
        }

        $qb = $this->getQueryBuilderForRenner($renner, $seizoen);
        $qb->andWhere('t.seizoen = ?2');
        $qb->setParameter("2", $seizoen);
        $qb->orderBy('t.id', 'DESC');
        return $qb->getQuery()->getResult();
    }

    public function findLastByRenner(Renner $renner)
    {
        $qb = $this->getQueryBuilderForRenner($renner);
        $qb->orderBy('t.id', 'DESC');
        $qb->setMaxResults(1);
        $res = $qb->getQuery()->getResult();
        return ( array_key_exists(0, $res) ) ? $res[0] : null;
    }

    private function getQueryBuilderForRenner($renner)
    {
        $qb = $this->createQueryBuilder("t");
        $qb->where('t.renner = ?1');
        $qb->setParameter('1', $renner);
        return $qb;
    }
    
    // TODO: teveel argumenten. maak losse methoden!
    public function getLatestWithInversion($seizoen = null, $types = array(), $limit = 20, $ploegNaar = null, $renner = null)
    {
        if (null === $seizoen) {
            $seizoen = $this->_em->getRepository("CyclearGameBundle:Seizoen")->getCurrent();
        }
        $qb = $this
            ->createQueryBuilder('t')
            ->add('select', '( SELECT r.naam FROM Cyclear\GameBundle\Entity\Transfer tr INNER JOIN CyclearGameBundle:Renner r WITH tr.renner = r 
                WHERE tr.identifier = t.identifier AND tr.id <> t.id ) AS inverse', true)
            ->where('t.ploegNaar IS NOT NULL')
            ->andWhere('t.seizoen = :seizoen')
            ->setParameters(array('seizoen' => $seizoen))
            ->setMaxResults($limit)
            ->orderBy('t.datum', 'DESC')
        ;
        if (null !== $ploegNaar) {
            $qb->andWhere('t.ploegNaar = :ploegNaar')->setParameter('ploegNaar', $ploegNaar);
        }
        if (null !== $renner) {
            $qb->andWhere('t.renner = :renner')->setParameter('renner', $renner);
        }
        if (!empty($types)) {
            $qb->andWhere('t.transferType IN ( :types )')->setParameter('types', $types);
        }
        return $qb->getQuery()->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_OBJECT);
    }

    public function findInversionRenner($transfer)
    {
        $qb = $this->createQueryBuilder("t")->where("t != :transfer")->andWhere("t.identifier = :identifier");
        $qb->setParameters(array(":transfer" => $transfer, ":identifier" => $transfer->getIdentifier()));
        $res = $qb->getQuery()->getResult();
        return ( array_key_exists(0, $res) ) ? $res[0]->getRenner() : null;
    }

    public function getTransferCountForUserTransfer($ploeg, $start, $end)
    {
        return $this->getTransferCountByType($ploeg, $start, $end, array(Transfer::USERTRANSFER, Transfer::ADMINTRANSFER));
    }

    public function getTransferCountByType($ploeg, $start, $end, $type)
    {
        if (!is_array($type)) {
            $type = array($type);
        }
        $query = $this->getEntityManager()
            ->createQuery("SELECT COUNT(t.id) AS freq FROM CyclearGameBundle:Transfer t 
                WHERE t.ploegNaar = :ploeg AND t.datum BETWEEN :start AND :end AND t.transferType IN( :type )")
            ->setParameters(array("type" => $type, "ploeg" => $ploeg, "start" => $start, "end" => $end));
        $res = $query->getSingleResult();
        return (int) $res['freq'];
    }

    public function findLastTransferForDate($renner, \DateTime $date)
    {
        $cloneDate = clone $date;
        $cloneDate->setTime("23", "59", "59");
        $qb = $this->createQueryBuilder("t")->where("t.renner = :renner")->andWhere("t.datum <= :datum")->
                setParameters(array("renner" => $renner, "datum" => $cloneDate))->orderBy("t.datum", "DESC")->setMaxResults(1);
        $res = $qb->getQuery()->getResult();
        if (count($res) == 0) {
            return null;
        }
        return $res[0];

        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addEntityResult('Cyclear\GameBundle\Entity\Transfer', 't');
        $rsm->addFieldResult('t', 'transferid', 'id');
        $rsm->addFieldResult('t', 'transferdatum', 'datum');
        //$rsm->addFieldResult('t', 'renner', 'renner');
        //$rsm->addFieldResult('t', 'ploegvan', 'ploegVan');
        //$rsm->addFieldResult('t', 'ploegnaar', 'ploegNaar');
        $cloneDate = clone $date;
        $cloneDate->setTime("23", "59", "59");
        $query = $this->getEntityManager()->createNativeQuery("SELECT 
                    t.id AS transferid, 
                    t.datum AS transferdatum,
                    t.renner_id AS renner,
                    t.ploegvan_id AS ploegvan,
                    t.ploegnaar_id AS ploegnaar
                    FROM transfer t
                LEFT JOIN renner r ON t.renner_id = r.id 
                LEFT JOIN ploeg p ON ploegnaar_id = p.id
                WHERE t.renner_id = :rennerid AND t.datum < :datum
                ORDER BY t.datum DESC LIMIT 1", $rsm)->setParameters(array('rennerid' => $renner->getId(), 'datum' => $cloneDate));
        $result = $query->getResult();
        if (count($result) == 0) {
            return null;
        }
        return $this->find($result[0]->getId());
    }
}
?>
