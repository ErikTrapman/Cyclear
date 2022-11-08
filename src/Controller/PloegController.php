<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Ploeg;
use App\Entity\Renner;
use App\Entity\Seizoen;
use App\Entity\Transfer;
use App\Entity\Uitslag;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Ploeg controller.
 *
 * @Route("/{seizoen}/ploeg")
 */
class PloegController extends AbstractController
{
    public static function getSubscribedServices()
    {
        return array_merge(['knp_paginator' => PaginatorInterface::class],
            parent::getSubscribedServices());
    }

    /**
     * Finds and displays a Ploeg entity.
     *
     * @Route ("/{id}/show", name="ploeg_show")
     *
     * @ParamConverter ("seizoen", options={"mapping": {"seizoen": "slug"}})
     *
     * @Template ()
     *
     * @return (Ploeg|Seizoen|\Doctrine\Persistence\ObjectRepository|\Symfony\Component\Form\FormView|float|int|mixed)[]|\Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @psalm-return \Symfony\Component\HttpFoundation\RedirectResponse|array{entity: Ploeg, renners: mixed, uitslagen: mixed, seizoen: Seizoen, transfers: mixed, rennerRepo: \Doctrine\Persistence\ObjectRepository<Renner>, transferUitslagen: mixed, lostDrafts: mixed, zeges: mixed, punten: mixed, draftRenners: mixed, draftPunten: float|int, form: \Symfony\Component\Form\FormView}
     */
    public function showAction(Request $request, Seizoen $seizoen, Ploeg $id): array|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $id;
        if (null === $entity) {
            throw $this->createNotFoundException('Unable to find Ploeg entity.');
        }
        $ploegRepo = $em->getRepository(Ploeg::class);
        $renners = $ploegRepo->getRennersWithPunten($entity);
        $uitslagRepo = $em->getRepository(Uitslag::class);
        $paginator = $this->get('knp_paginator');

        $uitslagen = $paginator->paginate(
            $uitslagRepo->getUitslagenForPloegQb($entity, $seizoen)->getQuery()->getResult(), $request->query->get('page', 1), 20
        );
        $transfers = $paginator->paginate($em->getRepository(Transfer::class)->getLatest(
            $seizoen, [Transfer::ADMINTRANSFER, Transfer::USERTRANSFER], 9999, $entity), $request->query->get('transferPage', 1), 20, ['pageParameterName' => 'transferPage']);
        $transferUitslagen = $paginator->paginate(
            $uitslagRepo->getUitslagenForPloegForNonDraftTransfersQb($entity, $seizoen)->getQuery()->getResult(), $request->query->get('transferResultsPage', 1), 20, ['pageParameterName' => 'transferResultsPage']
        );
        $lostDrafts = $paginator->paginate(
            $uitslagRepo->getUitslagenForPloegForLostDraftsQb($entity, $seizoen)->getQuery()->getResult(), $request->query->get('page', 1), 20
        );
        $zeges = $paginator->paginate(
            $uitslagRepo->getUitslagenForPloegByPositionQb($entity, 1, $seizoen)->getQuery()->getResult(), $request->query->get('zegeResultsPage', 1), 20, ['pageParameterName' => 'zegeResultsPage']
        );

        $rennerRepo = $em->getRepository(Renner::class);
        $punten = $uitslagRepo->getPuntenByPloeg($seizoen, $entity);
        $draftRenners = $ploegRepo->getDraftRennersWithPunten($entity, false);

        $form = $this->createFormBuilder($entity)
            ->add('memo', null, ['attr' => ['placeholder' => '...', 'rows' => 16]])
            ->add('save', SubmitType::class)
            ->getForm();
        if ('POST' === $request->getMethod()) {
            if ($form->handleRequest($request)->isValid()) {
                $em->flush();
                return $this->redirect($this->generateUrl('ploeg_show', ['id' => $entity->getId(), 'seizoen' => $seizoen->getSlug()]));
            }
        }

        return [
            'entity' => $entity,
            'renners' => $renners,
            'uitslagen' => $uitslagen,
            'seizoen' => $seizoen,
            'transfers' => $transfers,
            'rennerRepo' => $rennerRepo,
            'transferUitslagen' => $transferUitslagen,
            'lostDrafts' => $lostDrafts,
            'zeges' => $zeges,
            'punten' => $punten[0]['punten'],
            'draftRenners' => $draftRenners,
            'draftPunten' => array_sum(array_map(function ($el) {
                return $el['punten'];
            }, $draftRenners)),
            'form' => $form->createView(),
        ];
    }
}
