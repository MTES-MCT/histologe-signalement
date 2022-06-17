<?php

namespace App\Controller;

use App\Entity\Signalement;
use App\Entity\Situation;
use App\Repository\CritereRepository;
use App\Repository\PartenaireRepository;
use App\Repository\SignalementRepository;
use App\Repository\SituationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/stats')]
class BackStatsController extends AbstractController
{
    private function setFilters($request): array
    {
        return [
            'search' => $request->get('search') ?? null,
            'statuses' => $request->get('bo-filter-statut') ?? null,
            'cities' => $request->get('bo-filter-ville') ?? null,
            'partners' => $request->get('bo-filter-partenaires') ?? null,
            'criteres' => $request->get('bo-filter-criteres') ?? null,
            'allocs' => $request->get('bo-filter-allocs') ?? null,
            'housetypes' => $request->get('bo-filter-housetypes') ?? null,
            'declarants' => $request->get('bo-filter-declarants') ?? null,
            'proprios' => $request->get('bo-filter-proprios') ?? null,
            'interventions' => $request->get('bo-filter-interventions') ?? null,
            'avant1949' => $request->get('bo-filter-avant1949') ?? null,
            'enfantsM6' => $request->get('bo-filter-enfantsM6') ?? null,
            'handicaps' => $request->get('bo-filter-handicaps') ?? null,
            'affectations' => $request->get('bo-filter-affectations') ?? null,
            'visites' => $request->get('bo-filter-visites') ?? null,
            'delays' => $request->get('bo-filter-delays') ?? null,
            'scores' => $request->get('bo-filter-scores') ?? null,
            'dates' => $request->get('bo-filter-dates') ?? null,
            'page' => $request->get('page') ?? 1,
        ];
    }

    #[Route('/', name: 'back_statistique')]
    public function index(SignalementRepository $signalementRepository, CritereRepository $critereRepository, PartenaireRepository $partenaireRepository, Request $request, SituationRepository $situationRepository, EntityManagerInterface $entityManager): Response
    {
        $title = 'Statistiques';
        $dates = [];
        $totaux = ['open' => 0, 'closed' => 0, 'all' => 0];
        $villes = [];
        $filters = $this->setFilters($request);
        $signalements = $signalementRepository->findByStatusAndOrCityForUser($user ?? null, $filters, null)->getQuery()->getArrayResult();
        $criteres = $critereRepository->findAllList();
        return $this->render('back/statistique/index.html.twig', [
            'title' => $title,
            'dates' => $dates,
            'filter' => $filters,
            'totaux' => $totaux,
            'cities' => $signalementRepository->findCities($user ?? null),
            'partenaires' => $partenaireRepository->findAllList(),
            'criteres' => $criteres,
            'villes' => $villes
        ]);
    }
}