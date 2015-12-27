<?php

/*
 * This file is part of the Cyclear-game package.
 *
 * (c) Erik Trapman <veggatron@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cyclear\GameBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Cyclear\GameBundle\Entity\Wedstrijd,
    Cyclear\GameBundle\Form\WedstrijdType;

/**
 *
 * @Route("/admin/wedstrijd")
 */
class WedstrijdController extends Controller
{

    /**
     * @Route("/", name="admin_wedstrijd")
     * @Template("CyclearGameBundle:Wedstrijd/Admin:index.html.twig")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $query = $em->createQuery('SELECT w FROM CyclearGameBundle:Wedstrijd w ORDER BY w.id DESC');

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query, $this->get('request')->query->get('page', 1)/* page number */, 20/* limit per page */
        );
        return array('pagination' => $pagination);
    }


    /**
     * Displays a form to create a new Periode entity.
     *
     * @Route("/new", name="admin_wedstrijd_new")
     * @Template("CyclearGameBundle:Wedstrijd/Admin:new.html.twig")
     */
    public function newAction()
    {
        $entity = new Wedstrijd();
        $form = $this->createForm(new WedstrijdType(), $entity);

        return array(
            'entity' => $entity,
            'form' => $form->createView()
        );
    }

    /**
     * Creates a new Periode entity.
     *
     * @Route("/create", name="admin_wedstrijd_create")
     * @Method("post")
     */
    public function createAction()
    {
        $entity = new Wedstrijd();
        $request = $this->getRequest();
        $form = $this->createForm(new WedstrijdType(), $entity);
        $form->submit($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_wedstrijd'));
        }

        return array(
            'entity' => $entity,
            'form' => $form->createView()
        );
    }

    /**
     * Displays a form to edit an existing Periode entity.
     *
     * @Route("/{id}/edit", name="admin_wedstrijd_edit")
     * @Template("CyclearGameBundle:Wedstrijd/Admin:edit.html.twig")
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('CyclearGameBundle:Wedstrijd')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Wedstrijd entity.');
        }

        $editForm = $this->createForm(new WedstrijdType(), $entity);
        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity' => $entity,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Edits an existing Periode entity.
     *
     * @Route("/{id}/update", name="admin_wedstrijd_update")
     * @Method("post")
     */
    public function updateAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('CyclearGameBundle:Wedstrijd')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Wedstrijd entity.');
        }

        $editForm = $this->createForm(new WedstrijdType(), $entity);
        $deleteForm = $this->createDeleteForm($id);

        $request = $this->getRequest();

        $editForm->submit($request);

        if ($editForm->isValid()) {
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_wedstrijd_edit', array('id' => $id)));
        }

        return array(
            'entity' => $entity,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Deletes a Periode entity.
     *
     * @Route("/{id}/delete", name="admin_wedstrijd_delete")
     * @Method("post")
     */
    public function deleteAction($id)
    {
        $form = $this->createDeleteForm($id);
        $request = $this->getRequest();

        $form->submit($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository('CyclearGameBundle:Wedstrijd')->find($id);

            if (!$entity) {
                throw $this->createNotFoundException('Unable to find Wedstrijd entity.');
            }

            $em->remove($entity);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('admin_wedstrijd'));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm();
    }

}