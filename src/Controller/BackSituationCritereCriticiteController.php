<?php

namespace App\Controller;

use App\Entity\Critere;
use App\Entity\Criticite;
use App\Entity\Situation;
use App\Form\SituationType;
use App\Repository\SituationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/scc')]
class BackSituationCritereCriticiteController extends AbstractController
{

    #[Route('/', name: 'back_situation_critere_criticite_index')]
    public function index(SituationRepository $situationRepository)
    {
        $title = 'Listing situation, critère et criticité';
        return $this->render('back/scc/index.html.twig', [
            'title' => $title,
            'situations' => $situationRepository->findAllWithCritereAndCriticite(),
        ]);
    }

    #[Route('/new', name: 'back_situation_critere_criticite_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $title = 'Nouvelle situation';
        if (!$this->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('back_index');
        $situation = new Situation();
        $form = $this->createForm(SituationType::class, $situation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            self::checkFormExtraData($form,$situation,$entityManager);
            $entityManager->persist($situation);
            $entityManager->flush();
            return $this->redirectToRoute('back_situation_critere_criticite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('back/scc/edit.html.twig', [
            'title' => $title,
            'situation' => $situation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'back_situation_critere_criticite_edit')]
    public function editSituation(Situation $situation, Request $request, EntityManagerInterface $entityManager)
    {
        $title = 'Edition situation ' . $situation->getLabel();
        if (!$this->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('back_index');
        $form = $this->createForm(SituationType::class, $situation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            self::checkFormExtraData($form, $situation, $entityManager);
            $entityManager->flush();
            return $this->redirectToRoute('back_situation_critere_criticite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('back/scc/edit.html.twig', [
            'title' => $title,
            'situation' => $situation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{critere}/delete', name: 'back_situation_critere_delete', methods: ['POST'])]
    public function deleteCritereAndCriticite(Request $request,Critere $critere, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('back_index');
        if ($this->isCsrfTokenValid('situation_critere_delete_'.$critere->getId(), $request->request->get('_token'))) {
            $critere->setIsArchive(true);
            $critere->getCriticites()->map(function (Criticite $criticite)use ($entityManager){
                $criticite->setIsArchive(true);
                $entityManager->persist($criticite);
            });
            $entityManager->persist($critere);
            $entityManager->flush();
            return $this->json(['response'=>'success']);
        }
        return $this->json(['response'=>'error'],400);
    }

    #[Route('/{id}/delete', name: 'back_situation_delete', methods: ['POST'])]
    public function deleteSituation(Request $request,Situation $situation, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN'))
            return $this->redirectToRoute('back_index');
        if ($this->isCsrfTokenValid('situation_delete_'.$situation->getId(), $request->request->get('_token'))) {
            $situation->setIsArchive(true);
            $situation->getCriteres()->map(function (Critere $critere)use ($entityManager){
                $critere->setIsArchive(true);
                $critere->getCriticites()->map(function (Criticite $criticite)use ($entityManager){
                    $criticite->setIsArchive(true);
                    $entityManager->persist($criticite);
                });
                $entityManager->persist($critere);
            });
            $entityManager->persist($situation);
            $entityManager->flush();
            $this->addFlash('success','Situation supprimée avec succès !');
        } else
            $this->addFlash('error','Une erreur est survenue lors de la suppression !');
        return $this->redirectToRoute('back_situation_critere_criticite_index');
    }

    private static function checkFormExtraData(FormInterface $form, Situation $situation, EntityManagerInterface $entityManager)
    {
        if ($form->getExtraData()['criteres'])
            foreach ($form->getExtraData()['criteres'] as $idCritere => $critereData) {
                if ($idCritere !== 'new') {
                    $critereSituation = $situation->getCriteres()->filter(function (Critere $critere) use ($idCritere) {
                        if ($critere->getId() === $idCritere)
                            return $critere;
                    });
                    if (!$critereSituation->isEmpty()) {
                        /** @var Critere $critere */
                        $critere = $critereSituation->first();
                        $critere->setLabel($critereData['label']);
                        $critere->setDescription($critereData['description']);
                        $critere->setModifiedAt(new \DateTimeImmutable());
                        $entityManager->persist($critere);
                        foreach ($critereData['criticites'] as $idCriticite => $criticiteData) {
                            $criticiteCritereSituation = $critere->getCriticites()->filter(function (Criticite $criticite) use ($idCriticite) {
                                if ($criticite->getId() === $idCriticite)
                                    return $criticite;
                            });
                            if (!$criticiteCritereSituation->isEmpty()) {
                                /** @var Criticite $criticite */
                                $criticite = $criticiteCritereSituation->first();
                                $criticite->setLabel($criticiteData['label']);
                                $criticite->setModifiedAt(new \DateTimeImmutable());
                                $entityManager->persist($criticite);
                            }
                        }
                    }
                } else {
                    foreach ($critereData as $newCritereData)
                    {
                        $critere = new Critere();
                        $critere->setSituation($situation);
                        $critere->setLabel($newCritereData['label']);
                        $critere->setDescription($newCritereData['description']);
                        $critere->setModifiedAt(new \DateTimeImmutable());
                        $entityManager->persist($critere);
                        foreach ($newCritereData['criticites'] as $newCriticiteData) {
                            $criticite = new Criticite();
                            $criticite->setLabel($newCriticiteData['label']);
                            $criticite->setScore($newCriticiteData['score']);
                            $criticite->setCritere($critere);
                            $entityManager->persist($criticite);
                        }
                    }
                }
            }
    }
}