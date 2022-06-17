<?php

namespace App\Controller;

use App\Entity\Affectation;
use App\Entity\Criticite;
use App\Entity\Signalement;
use App\Repository\AffectationRepository;
use App\Repository\CritereRepository;
use App\Repository\PartenaireRepository;
use App\Repository\SignalementRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\SearchFilterService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo')]
class BackController extends AbstractController
{
    private $req;
    private $iterator;

    #[Route('/', name: 'back_index')]
    public function index(EntityManagerInterface $em,
                          CritereRepository      $critereRepository,
                          UserRepository         $userRepository,
                          SignalementRepository  $signalementRepository,
                          Request                $request,
                          AffectationRepository  $affectationRepository,
                          PartenaireRepository   $partenaireRepository,
                          TagRepository          $tagsRepository
    ): Response
    {
        $title = 'Administration - Tableau de bord';
        $user = null;
        if (!$this->isGranted('ROLE_ADMIN_PARTENAIRE'))
            $user = $this->getUser();
        $searchService = new SearchFilterService();
        $filters = $searchService->setRequest($request)->setFilters()->getFilters();
        if ($user || $filters['partners'] || $filters['affectations']) {
            $this->req = $affectationRepository->findByStatusAndOrCityForUser($user, $filters, $request->get('export'));
            if (!$request->get('export'))
                $this->iterator = $this->req->getIterator()->getArrayCopy();
            if ($user && $user->getPartenaire()) {
                $counts = $affectationRepository->countByStatusForUser($user);
                $signalementsCount = [
                    Signalement::STATUS_NEED_VALIDATION => $counts[0] ?? ['count' => 0],
                    Signalement::STATUS_ACTIVE => $counts[1] ?? ['count' => 0],
                    Signalement::STATUS_CLOSED => ['count' => ($counts[3]['count'] ?? 0) + ($counts[2]['count'] ?? 0)],
                ];
                $signalementsCount['total'] = count($this->req);
                $status = [
                    Affectation::STATUS_WAIT => Signalement::STATUS_NEED_VALIDATION,
                    Affectation::STATUS_ACCEPTED => Signalement::STATUS_ACTIVE,
                    Affectation::STATUS_CLOSED => Signalement::STATUS_CLOSED,
                    Affectation::STATUS_REFUSED => Signalement::STATUS_CLOSED,
                ];
                foreach ($this->iterator as $item)
                    $item->getSignalement()->setStatut((int)$status[$item->getStatut()]);
            }
        } else {
            $this->req = $signalementRepository->findByStatusAndOrCityForUser($user, $filters, $request->get('export'));
            $signalementsCount = $signalementRepository->countByStatus();
        }
        if (!$user) {
            $criteria = new Criteria();
            $criteria->where(Criteria::expr()->neq('statut', 7));
            $signalementsCount['total'] = $signalementRepository->matching($criteria)->count();
        }

        $signalements = [
            'list' => $this->req,
            'total' => count($this->req),
            'page' => (int)$filters['page'],
            'pages' => (int)ceil(count($this->req) / 30),
            'counts' => $signalementsCount ?? []
        ];

        if ($request->get('pagination'))
            return $this->stream('back/table_result.html.twig', ['filters' => $filters, 'signalements' => $signalements]);
        $criteres = $critereRepository->findAllList();
        if ($this->isGranted('ROLE_ADMIN_TERRITOIRE') && $request->get('export') && $this->isCsrfTokenValid('export_token_' . $this->getUser()->getId(), $request->get('_token'))) {
            return $this->export($this->req, $em);
        }
        $users = [
            'active' => $userRepository->count(['statut' => 1]),
            'inactive' => $userRepository->count(['statut' => 0]),
        ];

        return $this->render('back/index.html.twig', [
            'title' => $title,
            'filters' => $filters,
            'cities' => $signalementRepository->findCities($user),
            'partenaires' => $partenaireRepository->findAllList(),
            'signalements' => $signalements,
            'users' => $users,
            'criteres' => $criteres,
            'tags' => $tagsRepository->findAllActive()
        ]);
    }

    #[Route('/_json', name: 'back_json_convert')]
    public function jsonToEntity(Request $request)
    {
        if ($request->isMethod('POST'))
            return $this->forward('App\Controller\FrontSignalementController::envoi', ['signalement' => json_decode($request->get('json'), true)]);
        return new Response('<form method="POST" style="width: 100%;height: calc(100vh - 50px)"><textarea name="json" id="" style="width: 100%;height: calc(100% - 50px)"></textarea><hr><button style="width: 100%;height: 50px;">OK</button></form>');
    }


    private function export($signalements, EntityManagerInterface $em): Response
    {
        $tmpFileName = (new Filesystem())->tempnam(sys_get_temp_dir(), 'sb_');
        $tmpFile = fopen($tmpFileName, 'wb+');
        $headers = $em->getClassMetadata(Signalement::class)->getFieldNames();
        fputcsv($tmpFile, array_merge($headers, ['situations', 'criteres']), ';');
        foreach ($signalements as $signalement) {
            if ($signalement instanceof Affectation)
                $signalement = $signalement->getSignalement();
            $data = [];
            foreach ($headers as $header) {

                $method = 'get' . ucfirst($header);
                if ($header === "documents" || $header === "photos") {
                    $items = $signalement->$method();
                    if (!$items)
                        $data[] = "SANS";
                    else {
                        $arr = [];
                        foreach ($items as $item)
                            $arr[] = $item['titre'] ?? $item['file'] ?? $item;
                        $data[] = implode(",\r\n", $arr);
                    }
                } elseif ($header === "statut") {
                    $statut = match ($signalement->$method()) {
                        Signalement::STATUS_NEED_VALIDATION => 'A VALIDER',
                        Signalement::STATUS_ACTIVE => 'EN COURS',
                        Signalement::STATUS_CLOSED => 'CLOS',
                        Signalement::STATUS_REFUSED => 'REFUSE',
                        default => $signalement->$method(),
                    };
                    $data[] = $statut;
                } elseif ($header === "geoloc" && !empty($signalement->$method()['lat']) && !empty($signalement->$method()['lng'])) {
                    $data[] = "LAT: " . $signalement->$method()['lat'] . ' LNG: ' . $signalement->$method()['lng'];
                } elseif ($signalement->$method() instanceof \DateTimeImmutable || $signalement->$method() instanceof \DateTime)
                    $data[] = $signalement->$method()->format('d.m.Y');
                elseif (is_bool($signalement->$method()))
                    $data[] = $signalement->$method() ? 'OUI' : 'NON';
                elseif (!is_array($signalement->$method()) && !($signalement->$method() instanceof ArrayCollection))
                    $data[] = str_replace(';', '', $signalement->$method());
                elseif ($signalement->$method() == "")
                    $data[] = "N/R";
                else
                    $data[] = "[]";
            }
            $situations = $criteres = new ArrayCollection();
            $signalement->getCriticites()->filter(function (Criticite $criticite) use ($situations, $criteres) {
                $labels = ['DANGER', 'MOYEN', 'GRAVE', 'TRES GRAVE'];
                $critere = $criticite->getCritere();
                $situation = $criticite->getCritere()->getSituation();
                $critereAndCriticite = $critere->getLabel() . ' (' . $labels[$criticite->getScore()] . ')';
                if (!$situations->contains($situation->getLabel()))
                    $situations->add($situation->getLabel());
                if (!$criteres->contains($critereAndCriticite))
                    $situations->add($critereAndCriticite);
            });
            $data[] = implode(",\r\n", $situations->toArray());
            $data[] = implode(",\r\n", $criteres->toArray());
            fputcsv($tmpFile, $data, ';');
        }
        fclose($tmpFile);
        $response = $this->file($tmpFileName, 'dynamic-csv-file.csv');
        $response->headers->set('Content-type', 'application/csv');
        return $response;
    }
}
