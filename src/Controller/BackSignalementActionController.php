<?php

namespace App\Controller;

use App\Entity\Affectation;
use App\Entity\Signalement;
use App\Entity\Suivi;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\PartenaireRepository;
use App\Service\AffectationCheckerService;
use App\Service\EsaboraService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/bo/s')]
class BackSignalementActionController extends AbstractController
{
    private AffectationCheckerService $checker;

    public function __construct(AffectationCheckerService $affectationCheckerService)
    {
        $this->checker = $affectationCheckerService;
    }

    #[Route('/{uuid}/validation/response', name: 'back_signalement_validation_response', methods: "GET")]
    public function validationResponseSignalement(Signalement $signalement, Request $request, ManagerRegistry $doctrine, UrlGeneratorInterface $urlGenerator, NotificationService $notificationService): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE'))
            return $this->redirectToRoute('back_index');
        if ($this->isCsrfTokenValid('signalement_validation_response_' . $signalement->getId(), $request->get('_token'))
            && $response = $request->get('signalement-validation-response')) {
            if (isset($response['accept'])) {
                $statut = Signalement::STATUS_ACTIVE;
                $description = 'validé';
                $signalement->setValidatedAt(new \DateTimeImmutable());
                $signalement->setCodeSuivi(md5(uniqid()));
                $notificationService->send(NotificationService::TYPE_SIGNALEMENT_VALIDE, [$signalement->getMailDeclarant(), $signalement->getMailOccupant()], [
                    'signalement' => $signalement,
                    'lien_suivi' => $urlGenerator->generate('front_suivi_signalement', ['code' => $signalement->getCodeSuivi()], 0)
                ]);

            } else {
                $statut = Signalement::STATUS_REFUSED;
                $description = 'cloturé car non-valide avec le motif suivant :<br>' . $response['suivi'];
                $notificationService->send(NotificationService::TYPE_SIGNALEMENT_REFUSE, [$signalement->getMailDeclarant(), $signalement->getMailOccupant()], [
                    'signalement' => $signalement,
                    'motif' => $response['suivi']
                ]);

            }
            $suivi = new Suivi();
            $suivi->setSignalement($signalement);
            $suivi->setDescription('Signalement ' . $description);
            $suivi->setCreatedBy($this->getUser());
            $suivi->setIsPublic(true);
            $signalement->setStatut($statut);
            $doctrine->getManager()->persist($signalement);
            $doctrine->getManager()->persist($suivi);
            $doctrine->getManager()->flush();

            $this->addFlash('success', 'Statut du signalement mis à jour avec succés !');
        } else
            $this->addFlash('error', "Une erreur est survenue...");
        return $this->redirectToRoute('back_signalement_view', ['uuid' => $signalement->getUuid()]);
    }

    #[Route('/{uuid}/suivi/add', name: 'back_signalement_add_suivi', methods: "POST")]
    public function addSuiviSignalement(Signalement $signalement, Request $request, ManagerRegistry $doctrine, NotificationService $notificationService, UrlGeneratorInterface $urlGenerator): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_PARTENAIRE') && !$this->checker->check($signalement, $this->getUser()))
            return $this->redirectToRoute('back_index');
        if ($this->isCsrfTokenValid('signalement_add_suivi_' . $signalement->getId(), $request->get('_token'))
            && $form = $request->get('signalement-add-suivi')) {
            $suivi = new Suivi();
            $content = $form['content'];
            $content = preg_replace('/<p[^>]*>/', '', $content); // Remove the start <p> or <p attr="">
            $content = str_replace('</p>', '<br />', $content); // Replace the end
            $suivi->setDescription($content);
            $suivi->setIsPublic($form['isPublic']);
            $suivi->setSignalement($signalement);
            $suivi->setCreatedBy($this->getUser());
            $doctrine->getManager()->persist($suivi);
            $doctrine->getManager()->flush();
            $this->addFlash('success', 'Suivi publié avec succès !');
        } else
            $this->addFlash('error', 'Une erreur est survenu lors de la publication');
        return $this->redirect($this->generateUrl('back_signalement_view', ['uuid' => $signalement->getUuid()]) . '#suivis');
    }

    #[Route('/{uuid}/affectation/toggle', name: 'back_signalement_toggle_affectation')]
    public function toggleAffectationSignalement(Signalement $signalement, EsaboraService $esaboraService, ManagerRegistry $doctrine, Request $request, PartenaireRepository $partenaireRepository, NotificationService $notificationService): RedirectResponse|JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE') && !$this->checker->check($signalement, $this->getUser()))
            return $this->json(['status' => 'denied'], 400);
        if ($this->isCsrfTokenValid('signalement_affectation_' . $signalement->getId(), $request->get('_token'))) {
            $data = $request->get('signalement-affectation');
            if (isset($data['partenaires'])) {
                $postedPartenaire = $data['partenaires'];
                $alreadyAffectedPartenaire = $signalement->getAffectations()->map(function (Affectation $affectation) {
                    return $affectation->getPartenaire()->getId();
                })->toArray();
                $partenairesToAdd = array_diff($postedPartenaire, $alreadyAffectedPartenaire);
                $partenairesToRemove = array_diff($alreadyAffectedPartenaire, $postedPartenaire);
                foreach ($partenairesToAdd as $partenaireIdToAdd) {
                    $partenaire = $partenaireRepository->find($partenaireIdToAdd);
                    $affectation = new Affectation();
                    $affectation->setSignalement($signalement);
                    $affectation->setPartenaire($partenaire);
                    $affectation->setAffectedBy($this->getUser());
                    $doctrine->getManager()->persist($affectation);
                    if ($partenaire->getEsaboraToken() && $partenaire->getEsaboraUrl())
                        $esaboraService->post($affectation);
                }
                foreach ($partenairesToRemove as $partenaireIdToRemove) {
                    $partenaire = $partenaireRepository->find($partenaireIdToRemove);
                    $signalement->getAffectations()->filter(function (Affectation $affectation) use ($doctrine, $partenaire) {
                        if ($affectation->getPartenaire()->getId() === $partenaire->getId())
                            $doctrine->getManager()->remove($affectation);
                    });
                }
            } else {
                $signalement->getAffectations()->filter(function (Affectation $affectation) use ($doctrine) {
                    $doctrine->getManager()->remove($affectation);
                });
            }

            $doctrine->getManager()->flush();
            $this->addFlash('success', 'Les affectations ont bien été effectuées.');
            return $this->json(['status' => 'success']);
        }
        return $this->json(['status' => 'denied'], 400);
    }

    #[Route('/{uuid}/reopen', name: 'back_signalement_reopen')]
    public function reopenSignalement(Signalement $signalement, Request $request, ManagerRegistry $doctrine): RedirectResponse|JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE') && !$this->checker->check($signalement, $this->getUser()))
            return $this->json(['status' => 'denied'], 400);
        if ($this->isCsrfTokenValid('signalement_reopen_' . $signalement->getId(), $request->get('_token')) && $response = $request->get('signalement-action')) {
            if ($this->isGranted('ROLE_ADMIN_TERRITOIRE') && isset($response['reopenAll'])) {
                $signalement->setStatut(Signalement::STATUS_ACTIVE);
                $doctrine->getManager()->persist($signalement);
                $signalement->getAffectations()->filter(function (Affectation $affectation) use ($signalement, $doctrine) {
                    $affectation->setStatut(Affectation::STATUS_WAIT) && $doctrine->getManager()->persist($affectation);
                });
                $reopenFor = 'tous les partenaires';
            } else {
                $this->getUser()->getPartenaire()->getAffectations()->filter(function (Affectation $affectation) use ($signalement, $doctrine) {
                    if ($affectation->getSignalement()->getId() === $signalement->getId())
                        $affectation->setStatut(Affectation::STATUS_WAIT) && $doctrine->getManager()->persist($affectation);
                });
                $reopenFor = mb_strtoupper($this->getUser()->getPartenaire()->getNom());
            }
            $suivi = new Suivi();
            $suivi->setSignalement($signalement);
            $suivi->setDescription('Signalement rouvert pour ' . $reopenFor);
            $suivi->setCreatedBy($this->getUser());
            $suivi->setIsPublic(true);
            $doctrine->getManager()->persist($suivi);
            $doctrine->getManager()->flush();
            $this->addFlash('success', 'Signalement rouvert avec succés !');
        } else {
            $this->addFlash('error', 'Erreur lors de la réouverture du signalement! ');
        }
        return $this->redirectToRoute('back_signalement_view', ['uuid' => $signalement->getUuid()]);
    }

    #[Route('/{uuid}/affectation/{user}/response', name: 'back_signalement_affectation_response', methods: "GET")]
    public function affectationResponseSignalement(Signalement $signalement, User $user, Request $request, ManagerRegistry $doctrine): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_PARTENAIRE') && !$this->checker->check($signalement, $this->getUser()))
            return $this->redirectToRoute('back_index');
        if ($this->isCsrfTokenValid('signalement_affectation_response_' . $signalement->getId(), $request->get('_token'))
            && $response = $request->get('signalement-affectation-response')) {
            if (isset($response['accept']))
                $statut = Affectation::STATUS_ACCEPTED;
            else {
                $motifRefus = $response['suivi'];
                $statut = Affectation::STATUS_REFUSED;
                $motifRefus = preg_replace('/<p[^>]*>/', '', $motifRefus); // Remove the start <p> or <p attr="">
                $motifRefus = str_replace('</p>', '<br>', $motifRefus); // Replace the end
                $suivi = new Suivi();
                $suivi->setDescription('Le signalement à été refusé avec le motif suivant:<br> ' . $motifRefus);
                $suivi->setCreatedBy($this->getUser());
                $suivi->setSignalement($signalement);
                $doctrine->getManager()->persist($suivi);
            }
            $affectation = $doctrine->getRepository(Affectation::class)->findOneBy(['partenaire' => $user->getPartenaire(), 'signalement' => $signalement]);
            $affectation->setStatut($statut);
            $affectation->setAnsweredAt(new \DateTimeImmutable());
            $affectation->setAnsweredBy($this->getUser());
            $doctrine->getManager()->persist($affectation);
            $doctrine->getManager()->flush();
            $this->addFlash('success', 'Affectation mise à jour avec succès !');
        } else
            $this->addFlash('error', "Une erreur est survenu lors de l'affectation");
        return $this->redirectToRoute('back_signalement_view', ['uuid' => $signalement->getUuid()]);
    }

    #[Route('/{uuid}/switch', name: "back_signalement_switch_value", methods: "POST")]
    public function switchValue(Signalement $signalement, Request $request, EntityManagerInterface $entityManager): RedirectResponse|JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE') && !$this->checker->check($signalement, $this->getUser()))
            return $this->json(['status' => 'denied'], 400);
        if ($this->isCsrfTokenValid('signalement_switch_value_' . $signalement->getUuid(), $request->get('_token'))) {
            $return = 0;
            $item = $request->get('item');
            $getMethod = 'get' . $item;
            $setMethod = 'set' . $item;
            $value = $request->get('value');
            if ($item === "Tag") {
                $tag = $entityManager->getRepository(Tag::class)->find((int)$value);
                if ($signalement->getTags()->contains($tag))
                    $signalement->removeTag($tag);
                else {
                    $signalement->addTag($tag);
                }
            } else {
                if ($item === "DateVisite") {
                    $value = new \DateTimeImmutable($value);
                    $item = 'La date de visite';
                }
                if (!$value) {
                    $value = !(int)$signalement->$getMethod() ?? 1;
                    $return = 1;
                }

                $signalement->$setMethod($value);

            }
            $entityManager->persist($signalement);
            $entityManager->flush();
            if ($item === 'CodeProcedure')
                $item = 'Le type de procédure';
            if (is_bool($value) || $item === 'Tag')
                return $this->json(['response' => 'success', 'return' => $return]);
            $this->addFlash('success', $item . ' a bien été enregistré !');
            return $this->redirectToRoute('back_signalement_view', ['uuid' => $signalement->getUuid()]);
        }
        return $this->json(['response' => 'error'], 400);
    }

    //this function create a new tag
    //use Request to get label of the new tag
    #[Route('/newtag', name: "back_tag_create", methods: "POST")]
    public function createTag(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE') || !$this->isCsrfTokenValid('signalement_create_tag', $request->get('_token')))
            return $this->redirectToRoute('back_index');
        $label = $request->get('new-tag-label');
        $tag = new Tag();
        $tag->setLabel($label);
        $entityManager->persist($tag);
        $entityManager->flush();
        return $this->json(['response' => 'success', 'tag' => $tag]);
    }
    //this function create a new tag
    //use Request to get label of the new tag
    #[Route('/deltag/{id}', name: "back_tag_delete", defaults: ["id"=>null], methods: "GET")]
    public function deleteTag(Tag $tag,Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE') || !$this->isCsrfTokenValid('signalement_delete_tag', $request->get('_token')))
            return $this->redirectToRoute('back_index');
        $tag->setIsArchive(true);
        $entityManager->persist($tag);
        $entityManager->flush();
        return $this->json(['response' => 'success']);
    }
}