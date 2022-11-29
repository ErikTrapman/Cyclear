<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserEditType;
use App\Form\UserType;
use Doctrine\Persistence\ManagerRegistry;
use FOS\UserBundle\Doctrine\UserManager;
use FOS\UserBundle\Model\UserManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * User controller.
 *
 * @Route("/admin/user")
 */
class UserController extends AbstractController
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly UserManagerInterface $userManager,
    ) {
    }

    /**
     * Lists all User entities.
     *
     * @Route ("/", name="admin_user")
     *
     * @Template ()
     *
     * @return User[][]
     *
     * @psalm-return array{entities: array<User>}
     */
    public function indexAction(): array
    {
        $em = $this->doctrine->getManager();

        $entities = $em->getRepository(User::class)->findAll();

        return ['entities' => $entities];
    }

    /**
     * Displays a form to create a new Ploeg entity.
     *
     * @Route ("/new", name="admin_user_new")
     *
     * @Template ()
     *
     * @return \Symfony\Component\Form\FormView[]
     *
     * @psalm-return array{form: \Symfony\Component\Form\FormView}
     */
    public function newAction(): array
    {
        $form = $this->createForm(UserType::class);

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * Creates a new User entity.
     *
     * @Route ("/create", name="admin_user_create", methods={"POST"})
     *
     * @return \Symfony\Component\Form\FormView[]|\Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @psalm-return \Symfony\Component\HttpFoundation\RedirectResponse|array{form: \Symfony\Component\Form\FormView}
     */
    public function createAction(Request $request): array|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        $form = $this->createForm(UserType::class);
        $userManager = $this->userManager;

        $user = $userManager->createUser();
        $user->setEnabled(true);

        $form->setData($user);
        // IMPORTANT. We vragen niet om een password in het formulier. Zet hier dus tenminste een wachtwoord!
        $user->setPlainPassword(uniqid());
        $form->handleRequest($request);
        if ($form->isValid()) {
            $userManager->updateUser($user);
            return $this->redirect($this->generateUrl('admin_user_edit', ['id' => $user->getId()]));
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * Displays a form to edit an existing User entity.
     *
     * @Route ("/{id}/edit", name="admin_user_edit")
     *
     * @Template ()
     *
     * @psalm-return array{entity: User, edit_form: \Symfony\Component\Form\FormView}
     * @param mixed $id
     * @return (User|\Symfony\Component\Form\FormView)[]
     */
    public function editAction($id): array
    {
        $em = $this->doctrine->getManager();

        $entity = $em->getRepository(User::class)->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }
        $editForm = $this->createForm(UserEditType::class, $entity);

        return [
            'entity' => $entity,
            'edit_form' => $editForm->createView(),
        ];
    }

    /**
     * Edits an existing User entity.
     *
     * @Route ("/{id}/update", name="admin_user_update", methods={"POST"})
     *
     * @Template ()
     *
     * @psalm-return \Symfony\Component\HttpFoundation\RedirectResponse|array{entity: User, edit_form: \Symfony\Component\Form\FormView}
     * @param mixed $id
     * @return (User|\Symfony\Component\Form\FormView)[]|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function updateAction(Request $request, $id): array|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        $em = $this->doctrine->getManager();

        $entity = $em->getRepository(User::class)->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }
        $editForm = $this->createForm(UserEditType::class, $entity);

//        // http://symfony.com/doc/master/cookbook/form/form_collections.html - Ensuring the database persistence
//        $originalPloegen = array();
//        // Create an array of the current Tag objects in the database
//        foreach ($entity->getPloeg() as $ploeg) {
//            $originalPloegen[] = $ploeg;
//        }

        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
//            this is now done in PloegController
//            $usermanager = $this->get('cyclear_game.manager.user');
//            //$usermanager->updatePloegen($editForm, $entity);
//            foreach ($entity->getPloeg() as $ploeg) {
//                foreach ($originalPloegen as $key => $toDel) {
//                    if ($toDel->getId() === $ploeg->getId()) {
//                        unset($originalPloegen[$key]);
//                    }
//                }
//                $usermanager->setOwnerAcl($entity, $ploeg);
//                $ploeg->setUser($entity);
//            }
//
//            // remove the relationship between the tag and the Task
//            foreach ($originalPloegen as $ploeg) {
//                // remove the Task from the Tag
//                $ploeg->setUser(null);
//                $usermanager->unsetOwnerAcl($entity, $ploeg);
//
//                // if it were a ManyToOne relationship, remove the relationship like this
//                // $tag->setTask(null);
//
//                $em->persist($ploeg);
//
//                // if you wanted to delete the Tag entirely, you can also do that
//                // $em->remove($tag);
//            }

            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_user_edit', ['id' => $id]));
        }

        return [
            'entity' => $entity,
            'edit_form' => $editForm->createView(),
        ];
    }
}
