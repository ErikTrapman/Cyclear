<?php

namespace Cyclear\GameBundle\Controller\Admin;

use Cyclear\GameBundle\Entity\Uitslag;
use Cyclear\GameBundle\Entity\Wedstrijd;
use Cyclear\GameBundle\EntityManager\RennerManager;
use Cyclear\GameBundle\EntityManager\UitslagManager;
use Cyclear\GameBundle\Form\UitslagConfirmType;
use Cyclear\GameBundle\Form\UitslagNewType;
use Cyclear\GameBundle\Form\UitslagType;
use Cyclear\GameBundle\Form\WedstrijdType;
use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 * @Route("admin/uitslag")
 *
 */
class UitslagController extends Controller
{

    /**
     * @Route("/", name="admin_uitslag")
     * @Template("CyclearGameBundle:Uitslag/Admin:index.html.twig")
     */
    public function indexAction()
    {

        $em = $this->getDoctrine()->getEntityManager();

        $query = $em->createQuery('SELECT w FROM CyclearGameBundle:Uitslag w ORDER BY w.id DESC');

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query, $this->get('request')->query->get('page', 1)/* page number */, 10/* limit per page */
        );
        return compact('pagination');
    }

    /**
     * @Route("/{uitslag}/edit", name="admin_uitslag_edit")
     * @Template("CyclearGameBundle:Uitslag/Admin:edit.html.twig")
     */
    public function editAction(Request $request, Uitslag $uitslag)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $seizoen = $uitslag->getSeizoen();
        $form = $this->createForm(new UitslagType(), $uitslag, array('seizoen' => $seizoen));
        if ('POST' === $request->getMethod()) {
            $form->bind($request);
            if ($form->isValid()) {
                $em->flush();
                return $this->redirect($this->generateUrl('admin_uitslag_edit', array('uitslag' => $uitslag->getId())));
            }
        }

        return array('form' => $form->createView(), 'entity' => $uitslag);
    }

    /**
     * Displays a form to create a new Periode entity.
     *
     * @Route("/create", name="admin_uitslag_create")
     * @Template("CyclearGameBundle:Uitslag/Admin:create.html.twig")
     */
    public function createAction(Request $request)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $uitslagManager = $this->get('cyclear_game.manager.uitslag');
        $wedstrijdManager = $this->get('cyclear_game.manager.wedstrijd');
        $crawlerManager = $this->get('eriktrapman_cqparser.crawler_manager');
        $options = array();
        $options['crawler_manager'] = $crawlerManager;
        $options['wedstrijd_manager'] = $wedstrijdManager;
        $options['uitslag_manager'] = $uitslagManager;
        $options['request'] = $request;
        $options['seizoen'] = $request->attributes->get('seizoen-object');
        $form = $this->createForm(new \Cyclear\GameBundle\Form\UitslagCreateType(), null, $options);
        if ($request->isXmlHttpRequest()) {
            $form->bind($request);

            $twig = $this->get('twig');
            $templateFile = "CyclearGameBundle:Uitslag/Admin:_ajaxTemplate.html.twig";
            $templateContent = $twig->loadTemplate($templateFile);

            // Render the whole template including any layouts etc
            $body = $templateContent->render(array("form" => $form->createView()));
            return new Response($body);
        }
        if ($request->getMethod() == 'POST') {
            $form->bind($request);
            $wedstrijd = $form->get('wedstrijd')->getData();
            $uitslagen = $form->get('uitslag')->getData();
            $em->persist($wedstrijd);
            foreach ($uitslagen as $uitslag) {
                if(null === $uitslag->getRenner()->getId()){
                    $em->persist($uitslag->getRenner());
                }
                $em->persist($uitslag);
            }
            $em->flush();
            $this->get('session')->getFlashBag()->add('notice', 'Wedstrijd `'.$wedstrijd->getNaam().'` succesvol verwerkt');
            return $this->redirect($this->generateUrl('admin_uitslag_create'));
        }
        return array('form' => $form->createView());
    }

}