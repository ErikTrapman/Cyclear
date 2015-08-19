<?php

/*
 * This file is part of the Cyclear-game package.
 *
 * (c) Erik Trapman <veggatron@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cyclear\GameBundle\Controller\User;

use Cyclear\GameBundle\Entity\Ploeg;
use Cyclear\GameBundle\Entity\Renner;
use Cyclear\GameBundle\Entity\Seizoen;
use Cyclear\GameBundle\Entity\Transfer;
use Cyclear\GameBundle\Form\Entity\UserTransfer;
use Cyclear\GameBundle\Form\TransferUserType;
use JMS\SecurityExtraBundle\Annotation\SecureParam;
use RuntimeException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Transfer controller.
 *
 * @Route("/user/{seizoen}/transfer")
 */
class TransferController extends Controller
{

    /**
     * My team.
     *
     * @Route("/ploeg/{id}/renner/{renner}", name="user_transfer")
     * @Template("CyclearGameBundle:Transfer/User:index.html.twig")
     * @ParamConverter("seizoen", options={"mapping": {"seizoen": "slug"}})
     * @ParamConverter("renner", class="CyclearGameBundle:Renner", options={"mapping": {"renner": "slug"}});
     * @SecureParam(name="id", permissions="OWNER")
     */
    public function indexAction(Seizoen $seizoen, Ploeg $id, Renner $renner)
    {
        $usermanager = $this->get('cyclear_game.manager.user');
        $em = $this->getDoctrine()->getManager();
        $ploeg = $id;
        if (null === $ploeg) {
            throw new RuntimeException("Unknown ploeg");
        }
        if (!$usermanager->isOwner($this->getUser(), $ploeg)) {
            throw new AccessDeniedHttpException("Dit is niet jouw ploeg");
        }
        $transferUser = new UserTransfer();
        $transferUser->setPloeg($ploeg);
        $transferUser->setSeizoen($seizoen);
        $transferUser->setDatum(new \DateTime());

        $options = array();
        $rennerPloeg = $em->getRepository("CyclearGameBundle:Renner")->getPloeg($renner, $seizoen);
        if ($rennerPloeg !== $ploeg) {
            if (null !== $rennerPloeg) {
                throw new AccessDeniedHttpException("Renner is niet in je ploeg");
            } else {
                $options['renner_in'] = $renner;
                $transferUser->setRennerIn($renner);
            }
        } else {
            $options['renner_uit'] = $renner;
            $transferUser->setRennerUit($renner);
        }
        $options['ploegRenners'] = $this->getDoctrine()->getRepository("CyclearGameBundle:Ploeg")->getRenners($ploeg);
        $options['ploeg'] = $ploeg;
        $form = $this->createForm(new TransferUserType(), $transferUser, $options);
        if ($this->getRequest()->getMethod() == 'POST') {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                $transferManager = $this->get('cyclear_game.manager.transfer');
                if ($rennerPloeg !== $ploeg) {
                    $transferManager->doUserTransfer($ploeg, $form->get('renner_uit')->getData(), $renner, $seizoen);
                } else {
                    $transferManager->doUserTransfer($ploeg, $renner, $form->get('renner_in')->getData(), $seizoen);
                }
                $em->flush();
                return new RedirectResponse($this->generateUrl("ploeg_show", array("seizoen" => $seizoen->getSlug(), "id" => $ploeg->getId())));
            }
        }

        $transferTypes = array(Transfer::ADMINTRANSFER, Transfer::USERTRANSFER);
        $periode = $em->getRepository("CyclearGameBundle:Periode")->getCurrentPeriode($seizoen);
        $transferInfo = $em->getRepository("CyclearGameBundle:Transfer")
            ->getTransferCountByType($ploeg, $periode->getStart(), $periode->getEind(), $transferTypes);

        return
            array(
                'ploeg' => $ploeg,
                'renner' => $renner,
                'form' => $form->createView(),
                'seizoen' => $seizoen,
                'transferInfo' => array(
                    'count' => $transferInfo,
                    'left' => $periode->getTransfers() - $transferInfo)
            );
    }
}