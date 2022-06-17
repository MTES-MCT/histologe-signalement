<?php

namespace App\Controller;

use App\Repository\CritereRepository;
use App\Repository\PartenaireRepository;
use App\Repository\SignalementRepository;
use App\Repository\TagRepository;
use App\Service\SearchFilterService;
use http\Encoding\Stream;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/cartographie')]
class BackCartographieController extends AbstractController
{

    #[Route('/', name: 'back_cartographie')]
    public function index(SignalementRepository $signalementRepository,TagRepository $tagsRepository, Request $request, CritereRepository $critereRepository, PartenaireRepository $partenaireRepository): Response
    {
        $title = 'Cartographie';
        $searchService = new SearchFilterService();
//        $filters = $searchService->setRequest($request)->getFilters();
        $filters =  $searchService->setRequest($request)->setFilters()->getFilters();
/*        dd($filters,$request->get('bo-filters-statuses'));
        dd($filters);*/
        $user = null;
        if (!$this->isGranted('ROLE_ADMIN_TERRITOIRE'))
            $user = $this->getUser();
        if ($request->get('load_markers')) {
           /* if ($this->isCsrfTokenValid('load_markers_' . (new \DateTimeImmutable())->format('dmYhi'), $request->headers->get('x-token')))*/
                return $this->json(['signalements' => $signalementRepository->findAllWithGeoData($user ?? null, $filters, (int)$request->get('offset'))]);
           /* else
                return $this->json(['error' => 'HSTLG_MAPMRKR_BTK'],400);*/
        }
//        dd($signalements->getQuery()->getResult());
//        $signalements['cities'] = $signalementRepository->findCities($user ?? null);

        return $this->render('back/cartographie/index.html.twig', [
            'title' => $title,
            'filters' => $filters,
            'cities' => $signalementRepository->findCities($user ?? null),
            'partenaires' => $partenaireRepository->findAllList(),
            'signalements' => [/*$signalements*/],
            'criteres' => $critereRepository->findAllList(),
            'tags'=>$tagsRepository->findAllActive()
        ]);
    }
}