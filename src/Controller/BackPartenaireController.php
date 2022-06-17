<?php

namespace App\Controller;

use App\Entity\Partenaire;
use App\Entity\User;
use App\Form\PartenaireType;
use App\Repository\PartenaireRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

#[Route('/bo/partenaire')]
class BackPartenaireController extends AbstractController
{
    private static function checkFormExtraData(FormInterface $form,Partenaire $partenaire,EntityManagerInterface $entityManager,LoginLinkHandlerInterface $loginLinkHandler,NotificationService $notificationService)
    {
        if (isset($form->getExtraData()['users']))
            foreach ($form->getExtraData()['users'] as $id => $userData) {
                if($id !== 'new'){
                    $userPartenaire = $partenaire->getUsers()->filter(function (User $user) use ($id) {
                        if ($user->getId() === $id)
                            return $user;
                    });
                    if (!$userPartenaire->isEmpty())
                    {
                        $user = $userPartenaire->first();
                        self::setUserData($user,$userData['nom'],$userData['prenom'],$userData['roles'],$userData['email'],$userData['isGenerique'],$userData['isMailingActive']);
                        $entityManager->persist($user);
                    }
                } else {
                    foreach ($userData as $newUserData)
                    {
                        $user = new User();
                        $user->setPartenaire($partenaire);
                        self::setUserData($user,$newUserData['nom'],$newUserData['prenom'],$newUserData['roles'],$newUserData['email'],$newUserData['isGenerique'],$newUserData['isMailingActive']);
                        $entityManager->persist($user);
                        $loginLinkDetails = $loginLinkHandler->createLoginLink($user);
                        $loginLink = $loginLinkDetails->getUrl();
                        $notificationService->send(NotificationService::TYPE_ACTIVATION,$user->getEmail(),['link'=>$loginLink]);

                    }
                }
            }
    }

    private static function setUserData(User $user, mixed $nom, mixed $prenom, mixed $roles, mixed $email,bool $isGenerique,bool $isMailingActive)
    {
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setIsGenerique($isGenerique);
        $user->setIsMailingActive($isMailingActive);
        $user->setRoles([$roles]);
        $user->setEmail($email);
    }

    #[Route('/', name: 'back_partenaire_index', methods: ['GET'])]
    public function index(PartenaireRepository $partenaireRepository): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE'))
            return $this->redirectToRoute('back_index');
        return $this->render('back/partenaire/index.html.twig', [
            'partenaires' => $partenaireRepository->findAllOrByInseeIfCommune(),
        ]);
    }

    #[Route('/new', name: 'back_partenaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager,LoginLinkHandlerInterface $loginLinkHandler,NotificationService $notificationService): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE'))
            return $this->redirectToRoute('back_index');
        $partenaire = new Partenaire();
        $form = $this->createForm(PartenaireType::class, $partenaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            self::checkFormExtraData($form,$partenaire,$entityManager,$loginLinkHandler,$notificationService);
            $entityManager->persist($partenaire);
            $entityManager->flush();
            return $this->redirectToRoute('back_partenaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('back/partenaire/edit.html.twig', [
            'partenaire' => $partenaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'back_partenaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Partenaire $partenaire, EntityManagerInterface $entityManager,LoginLinkHandlerInterface $loginLinkHandler,NotificationService $notificationService): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_PARTENAIRE'))
            return $this->redirectToRoute('back_index');
        $form = $this->createForm(PartenaireType::class, $partenaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            self::checkFormExtraData($form,$partenaire,$entityManager,$loginLinkHandler,$notificationService);
            $entityManager->flush();

            return $this->redirectToRoute('back_partenaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('back/partenaire/edit.html.twig', [
            'partenaire' => $partenaire,
            'partenaires'=> $entityManager->getRepository(Partenaire::class)->findAll(),
            'form' => $form,
        ]);
    }

    #[Route('/switchuser', name: 'back_partenaire_user_switch', methods: ['POST'])]
    public function switchUser(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_PARTENAIRE'))
            return $this->redirectToRoute('back_index');
        if ($this->isCsrfTokenValid('partenaire_user_switch', $request->request->get('_token')) &&$data = $request->get('user_switch')) {
            $partenaire = $entityManager->getRepository(Partenaire::class)->find($data['partenaire']);
            $user = $entityManager->getRepository(User::class)->find($data['user']);
            $user->setPartenaire($partenaire);
//            $user->setStatut(User::STATUS_ARCHIVE);
            $entityManager->persist($user);
            $entityManager->flush();
            $this->addFlash('success',$user->getNomComplet().' transféré avec succès !');
            return $this->redirectToRoute('back_partenaire_edit', ['id'=>$partenaire->getId()], Response::HTTP_SEE_OTHER);
        }
        $this->addFlash('error','Une erreur est survenue lors du transfert...');
        return $this->redirectToRoute('back_partenaire_index', [], Response::HTTP_SEE_OTHER);
    }


    #[Route('/{user}/delete', name: 'back_partenaire_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request,User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_PARTENAIRE'))
            return $this->redirectToRoute('back_index');
        if ($this->isCsrfTokenValid('partenaire_user_delete_'.$user->getId(), $request->request->get('_token'))) {
            $user->setStatut(User::STATUS_ARCHIVE);
            $entityManager->persist($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('back_partenaire_index', [], Response::HTTP_SEE_OTHER);
    }


    #[Route('/checkmail', name: 'back_partenaire_check_user_email', methods: ['POST'])]
    public function checkMail(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_USER_PARTENAIRE'))
            return $this->redirectToRoute('back_index');
        if ($this->isCsrfTokenValid('partenaire_checkmail', $request->request->get('_token'))) {
            if($entityManager->getRepository(User::class)->findOneBy(['email'=>$request->get('email')]))
                return $this->json(['error'=>'email_exist'],400);
        }
        return $this->json(['success'=>'email_ok']);
    }

    #[Route('/{id}', name: 'back_partenaire_delete', methods: ['POST'])]
    public function delete(Request $request, Partenaire $partenaire, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE'))
            return $this->redirectToRoute('back_index');
        if ($this->isCsrfTokenValid('partenaire_delete_' . $partenaire->getId(), $request->request->get('_token'))) {
            $partenaire->setIsArchive(true);
            foreach ($partenaire->getUsers() as $user)
                $user->setStatut(User::STATUS_ARCHIVE) && $entityManager->persist($user);
            $entityManager->persist($partenaire);
            $entityManager->flush();
        }

        return $this->redirectToRoute('back_partenaire_index', [], Response::HTTP_SEE_OTHER);
    }
}
