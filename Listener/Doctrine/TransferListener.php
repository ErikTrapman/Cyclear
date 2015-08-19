<?php

/*
 * This file is part of the Cyclear-game package.
 *
 * (c) Erik Trapman <veggatron@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cyclear\GameBundle\Listener\Doctrine;

use Cyclear\GameBundle\Entity\Transfer;
use Doctrine\ORM\Event\LifecycleEventArgs;

class TransferListener
{
    private $tweeter;

    public function __construct($tweeter)
    {
        $this->tweeter = $tweeter;
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();

        if ($entity instanceof Transfer) {
            if (null !== $entity->getPloegNaar() && $entity->getTransferType() != Transfer::DRAFTTRANSFER) {
                $inversion = $entity->getInversionTransfer();
                $ploegNaar = $entity->getPloegNaar()->getAfkorting();
                if(null !== $inversion){
                    $rennerUit = $inversion->getRenner()->getNaam();
                } else {
                    $rennerUit = '-';
                }
                $rennerIn = $entity->getRenner()->getNaam();
                $msg = sprintf('Transfer voor %s ~ [IN] %s, [UIT] %s', $ploegNaar, $rennerIn, $rennerUit);
                try { 
                    $this->tweeter->sendTweet($msg);
                } catch(\Exception $e){
                    // do nothing. Exception is logged
                }
            }
        }
    }
}