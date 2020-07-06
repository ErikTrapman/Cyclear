<?php

/*
 * This file is part of the Cyclear-game package.
 *
 * (c) Erik Trapman <veggatron@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EntityManager;

use App\Entity\Contract;

class ContractManager
{
    /**
     *
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    public function __construct($em)
    {
        $this->em = $em;
    }

    public function releaseRenner($renner, $seizoen, $einddatum)
    {
        $currentContract = $this->em->getRepository(Contract::class)->getCurrentContract($renner, $seizoen);
        if (null === $currentContract) {
            return true;
        }
        $currentContract->setEind($einddatum);
        $this->em->persist($currentContract);
        return true;
    }

    public function createContract($renner, $ploeg, $seizoen, $datum)
    {
        $c = new \App\Entity\Contract();
        $c->setPloeg($ploeg);
        $c->setRenner($renner);
        $c->setSeizoen($seizoen);
        $c->setStart($datum);
        $this->em->persist($c);
        return $c;
    }
}