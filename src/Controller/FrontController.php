<?php

namespace App\Controller;

use App\Entity\Affectation;
use App\Entity\Critere;
use App\Entity\Criticite;
use App\Entity\Partenaire;
use App\Entity\Signalement;
use App\Entity\Suivi;
use App\Entity\User;
use App\Form\ContactType;
use App\Repository\AffectationRepository;
use App\Repository\PartenaireRepository;
use App\Repository\SignalementRepository;
use App\Repository\UserRepository;
use App\Service\ConfigurationService;
use App\Service\CriticiteCalculatorService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineExtensions\Query\Mysql\Date;
use mysqli;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FrontController extends AbstractController
{

    #[Route('/replicapi', name: 'replicapi', host: 'localhost')]
    public function replicapi(Request $request, Filesystem $fsObject, SignalementRepository $signalementRepository, ManagerRegistry $doctrine, NotificationService $notificationService)
    {
        $meta = $doctrine->getManager()->getClassMetadata(Signalement::class);
        echo "<table>";
        $signalements = $signalementRepository->findAll();
        echo "<thead><tr>";
        foreach ($meta->getFieldNames() as $fieldName) {
            echo "<th>" . $fieldName . "</th>";
        }
        echo "</tr></thead><tbody>";

        foreach ($signalements as $signalement) {
            echo "<tr>";
            foreach ($meta->getFieldNames() as $fieldName) {
                $method = 'get' . ucfirst($fieldName);
                echo "<td>";
                if (!is_array($signalement->$method()) && !$signalement->$method() instanceof \DateTimeImmutable)
                    echo $signalement->$method();

                if ($signalement->$method() instanceof \DateTimeImmutable)
                    echo $signalement->$method()->format('d.m.Y h:i:s');
                echo "</td>";
            }
            echo "<td>";
            foreach ($signalement->getCriticites() as $criticite) {
                echo "<b>" . $criticite->getCritere()->getSituation()->getLabel() . "</b></br>";
                echo " - " . $criticite->getCritere()->getLabel() . "<br>";
                echo "&nbsp;&nbsp; - " . $criticite->getLabel() . "<hr>";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        die;
        foreach ($signalements as $signalement) {
            $details = $signalement->getDetails();
            $signalement->setDetails(str_replace(['/r/n', '\r\n', '<br />'], '<br>', $details));
            $doctrine->getManager()->persist($signalement);
            /*$signalement->setCodeSuivi(md5(uniqid()));
            */
        }
        $doctrine->getManager()->flush();
        die('ok');
        foreach ($signalementRepository->findAll() as $signalement) {
            $calculator = new CriticiteCalculatorService($signalement, $doctrine);
            $score = $calculator->calculate();
            $signalement->setScoreCreation($score);
            $doctrine->getManager()->persist($signalement);
        }
        $doctrine->getManager()->flush();
        die('ok');
        header("Access-Control-Allow-Origin: *");
        $current_dir_path = getcwd();
        $error = null;
        try {
            $new_file_path = $current_dir_path . "/test.txt";

            if (!$fsObject->exists($new_file_path)) {
                $fsObject->touch($new_file_path);
                $fsObject->chmod($new_file_path, 0777);
                $fsObject->dumpFile($new_file_path, $request->getContent());
            }
        } catch (IOExceptionInterface $exception) {
            $error = "Error creating file at" . $exception->getPath();
        }
        return $this->json(['response' => $error ?? 'Ok']);

    }

    #[Route('/dump/{offset}', name: 'dump', host: 'localhost')]
    public function dump($offset, SignalementRepository $signalementRepository, PartenaireRepository $partenaireRepository, UserRepository $userRepository, EntityManagerInterface $entityManager, ManagerRegistry $doctrine, AffectationRepository $affectationRepository): Response
    {
        /*foreach ($userRepository->findAll() as $user){
            if($user->getIsGenerique())
            {
                $partenaire = $user->getPartenaire();
                $partenaire->setEmail($user->getEmail());
                $user->setStatut(User::STATUS_ARCHIVE);
                $partenaire->getUsers()->filter(function (User $user)use ($doctrine){
                    $user->setIsMailingActive(false);
                    $doctrine->getManager()->persist($user);
                });
                $doctrine->getManager()->persist($partenaire);
                $doctrine->getManager()->persist($user);
            }
        }
        $doctrine->getManager()->flush();
        die('Generique OK');*/
        /*$count = 0;
        foreach ($signalementRepository->findAll() as $signalement)
        {
            foreach ($signalement->getPhotos() as $photo)
            {
               if(isset($photo['file']))
                   $count++;
            }
            foreach ($signalement->getDocuments() as $doc) {
                if(isset($doc['file']))
                    $count++;
            }
        }
        dd($count);*/
        ini_set('max_execution_time', '-1');
        $dbhost = "localhost";
        $dbuser = "root";
        $dbpass = "";
        $db = "dumppau";
        $conn = new mysqli($dbhost, $dbuser, $dbpass, $db) or die("Connect failed: %s\n" . $conn->error);
        $count = 0;

        /*$partenaires = $conn->query('SELECT * from hpartenaire')->fetch_all(MYSQLI_ASSOC);
        foreach ($partenaires as $partenaire)
        {
            $part = new Partenaire();
            $part->setId($partenaire['idHPartenaire']);
            $part->setNom($partenaire['libPartenaire']);
            $part->setIsCommune($partenaire['isCommune']);
            $part->setInsee([$partenaire['codeInsee']]);
            $entityManager->persist($part);
        }
        $entityManager->flush();
        die('Partenaires OK');*/
        /*$usersQuery = "SELECT * from husers_bo";
         $users = $conn->query($usersQuery)->fetch_all(MYSQLI_ASSOC);
         foreach ($users as $user) {
             $partenaire = $entityManager->getRepository(Partenaire::class)->find($user['idPartenaire']);
             $u = new User();
             $u->setPartenaire($partenaire);
             if (str_contains($user['nom_bo'], 'GéNéRIQUE')) {
                 $nom = str_replace('GéNéRIQUE', '', $user['nom_bo']);
                 $isGenerique = 1;
             } else {
                 $nom = $user['nom_bo'];
                 $isGenerique = 0;
             }
             $u->setId($user['id_userbo']);
             $u->setNom($nom);
             $u->setPrenom($user['prenom_bo']);
             $u->setEmail($user['courriel']);
             $u->setIsMailingActive($user['sendAlert'] ?? 0);
             $u->setIsGenerique($isGenerique);
             $u->setRoles(['ROLE_USER_PARTENAIRE']);
             $entityManager->persist($u);
         }
         $entityManager->flush();
        die('Users OK');*/
        $signalementQuery = "SELECT * from hsignalement_ s
    JOIN hadresse_ a ON s.idAdresse = a.idAdresse
    ORDER BY s.dtCreaSignalement LIMIT 100 OFFSET 300
    ";
        /*if ($offset !== 0)
            $signalementQuery .= 'OFFSET ' . $offset;*/
        $signalements = $conn->query($signalementQuery)->fetch_all(MYSQLI_ASSOC);
        foreach ($signalements as $signalement) {
            $sign = new Signalement();
            foreach ($signalement as $key => $value)
                if (mb_strtoupper($value) === 'O' || mb_strtoupper($value) === 'OUI')
                    $signalement[$key] = 1;
                elseif (mb_strtoupper($value) === 'N' || mb_strtoupper($value) === 'NON')
                    $signalement[$key] = 0;
                elseif (mb_strtoupper($value) === 'N/R')
                    $signalement[$key] = null;
            $dateCrea = new \DateTimeImmutable($signalement['dtCreaSignalement']);
            if (!empty($signalement['geolocalisation'])) {
                $curGeoloc = explode(', ', $signalement['geolocalisation']);
                $geoloc = ['lat' => $curGeoloc[1], 'lng' => $curGeoloc[0]];
            }
            $modeContactProprio = explode('/', $signalement['modeInfoProprio']);
            $sign->setReference($dateCrea->format('Y') . '-' . $signalement['refSign']);
            $sign->setNomOccupant($signalement['nomSign']);
            $sign->setPrenomOccupant($signalement['prenomSign'] ?? 'N/R');
            $sign->setMailOccupant($signalement['courriel']);
            $sign->setCodeProcedure($signalement['procedureSign'] ?? null);
            $sign->setTelOccupant($signalement['telephone']);
            $sign->setDetails(str_replace('/r/n', '<br>', $signalement['description']));
            $sign->setIsProprioAverti($signalement['proprio_info']);
            $sign->setModeContactProprio($modeContactProprio);
            $sign->setIsLogementSocial($signalement['logSoc']);
            $sign->setIsPreavisDepart($signalement['depart']);
            $sign->setIsCguAccepted($signalement['cgu']);
            if (isset($geoloc))
                $sign->setGeoloc($geoloc);
            $sign->setNbAdultes((int)$signalement['OccupantsAdultes']);
            $sign->setSuperficie((float)$signalement['surface']);
            $sign->setLoyer((float)$signalement['prof_montantLoyer']);
            $sign->setNomProprio(mb_strtoupper($signalement['prof_nomProp']) . ' ' . ucfirst($signalement['prof_prenomProp']));
            $sign->setTelProprio($signalement['prof_telProp']);
            $sign->setMailProprio($signalement['prof_mailProp']);
            $sign->setAdresseProprio($signalement['prof_adresseProp']);
            $sign->setNatureLogement($signalement['prof_natureLog']);
            $sign->setNbEnfantsM6((int)$signalement['prof_nbEnfM6']);
            $sign->setNbEnfantsP6((int)$signalement['prof_nbEnfP6']);
            $sign->setIsBailEnCours($signalement['prof_bail'] ?? 0);
            $sign->setIsRelogement($signalement['prof_demRelog'] ?? 0);
            $sign->setIsRefusIntervention(isset($signalement['prof_accVisit']) ? !$signalement['prof_accVisit'] : 0);
            $sign->setRaisonRefusIntervention($signalement['prof_refusVisit'] ?? 0);
            $sign->setIsNotOccupant(isset($signalement['occupDeclarant']) ? !$signalement['occupDeclarant'] : 0);
            $sign->setTypeLogement(mb_strtoupper($signalement['prof_typoLog'] ?? 'appartement'));
            $sign->setNomDeclarant($signalement['nomDeclarant'] ?? null);
            $sign->setPrenomDeclarant($signalement['prenomDeclarant'] ?? null);
            $sign->setMailDeclarant($signalement['courrielDeclarant'] ?? null);
            $sign->setTelDeclarant($signalement['telDeclarant'] ?? null);
            $sign->setStructureDeclarant($signalement['structureDeclarant'] ?? null);
            $sign->setMontantAllocation((float)$signalement['prof_montantAlloc']);
            $sign->setNumAppartOccupant($signalement['numLog']);
            $sign->setVilleOccupant($signalement['ville']);
            $sign->setCpOccupant($signalement['codepostal']);
            $sign->setInseeOccupant($signalement['codeinsee']);
            $sign->setEtageOccupant($signalement['etage']);
            $sign->setEscalierOccupant($signalement['escalier']);
            $sign->setAdresseAutreOccupant($signalement['autre']);
            $sign->setNumAllocataire($signalement['prof_numAlloc']);
            $sign->setIsSituationHandicap($signalement['prof_sitHandi'] ?? 0);
            $sign->setCreatedAt(new \DateTimeImmutable($signalement['dtCreaSignalement']));
            $sign->setIsRsa($signalement['prof_RSA'] ?? 0);
            $sign->setIsOccupantPresentVisite($signalement['prof_locPres']);
            $sign->setDateVisite($signalement['prof_dtVisit'] ? new \DateTimeImmutable($signalement['dtCreaSignalement']) : null);
            $sign->setAdresseOccupant($signalement['numeroRue'] . ' ' . $signalement['compNumRue'] . ' ' . $signalement['nomRue']);
//            $sign->setProprioAvertiAt($signalement['dtInfoProprio'] ? new \DateTimeImmutable($signalement['dtInfoProprio']) : null);
            $sign->setAnneeConstruction($signalement['anne_construct']);
            $sign->setTypeEnergieLogement($signalement['infoNrj']);
            $sign->setOrigineSignalement($signalement['prof_origine']);
            $sign->setSituationOccupant($signalement['prof_situation']);
//            $sign->setNaissanceOccupantAt($signalement['prof_dtNaiss'] ? new \DateTimeImmutable($signalement['prof_dtNaiss']) : null);
            $sign->setSituationProOccupant($signalement['prof_sitProf']);
            $sign->setIsFondSolidariteLogement($signalement['prof_fsl']);
            $sign->setIsDiagSocioTechnique($signalement['prof_diag']);
            $sign->setIsLogementCollectif($signalement['prof_logCollectif']);
            $sign->setIsConstructionAvant1948($signalement['prof_av1948']);
            $sign->setIsRisqueSurOccupation($signalement['prof_risqOcc']);
            $sign->setValidatedAt(null !== $signalement['dtPriseEnCharge'] ? new \DateTimeImmutable($signalement['dtPriseEnCharge']) : null);
            $sign->setIsAllocataire($signalement['prof_organismeLog']);
            $sign->setNomReferentSocial($signalement['prof_nomSocialRef']);
            $sign->setStructureReferentSocial($signalement['prof_strucSocial']);
            $sign->setMailSyndic($signalement['prof_mailSyndic'] ?? '');
            $sign->setNomSci($signalement['prof_nomSci'] ?? '');
            $sign->setNomRepresentantSci($signalement['prof_repSci'] ?? '');
            $sign->setTelSci($signalement['prof_telSci'] ?? '');
            $sign->setMailSci($signalement['prof_mailSci'] ?? '');
            $sign->setTelSyndic($signalement['prof_telSyndic'] ?? '');
            $sign->setNomSyndic($signalement['prof_nomSyndic'] ?? '');
            $sign->setNumeroInvariant($signalement['prof_invariant']);
            $sign->setNbPiecesLogement((int)$signalement['prof_nbPieces']);
            $sign->setNbChambresLogement((int)$signalement['prof_nbChambres']);
            $sign->setNbNiveauxLogement((int)$signalement['prof_nbNiveaux']);
            $sign->setNbOccupantsLogement((int)$signalement['prof_nbOccup']);
            $sign->setStatut($signalement['etat'] === "8" ? Signalement::STATUS_CLOSED : Signalement::STATUS_ACTIVE);
            $sign->setCodeSuivi(md5(uniqid()));
            $criteresQuery = "SELECT * FROM hsignalement_hcritere h where h.idSignalement = '" . $signalement['idSignalement'] . "'";
            $criteres = $conn->query($criteresQuery)->fetch_all(MYSQLI_ASSOC);
            if ($criteres) {
                foreach ($criteres as $critere) {
                    $critereEntity = $entityManager->getRepository(Critere::class)->find($critere['idCritere']);
                    $criticiteEntity = $entityManager->getRepository(Criticite::class)->find($critere['idCriticite']);
                    $situationEntity = $critereEntity->getSituation();
                    $sign->addCritere($critereEntity);
                    if (empty($criticiteEntity))
                        $criticiteEntity = $entityManager->getRepository(Criticite::class)->find(47);
                    $sign->addCriticite($criticiteEntity);
                    $sign->addSituation($situationEntity);
                }
            }
            $calculator = new CriticiteCalculatorService($sign, $doctrine);
            $sign->setScoreCreation($calculator->calculate());
            //END CRITERE,CRITICITE,SITUATION
            //END SIGNALEMENT
            $docsArr = $photosArr = [];
            $docsReq = "SELECT * from hdoc h where h.idSignalement = '" . $signalement['idSignalement'] . "'";
            $photosReq = "SELECT * from hphoto h where h.idSignalement = '" . $signalement['idSignalement'] . "'";
            $docs = $conn->query($docsReq)->fetch_all(MYSQLI_ASSOC);
            $photos = $conn->query($photosReq)->fetch_all(MYSQLI_ASSOC);
            if ($docs)
                foreach ($docs as $doc) {
                    $docsArr[] = ['titre' => $doc['titreInitDoc'], 'file' => $doc['titreDoc'], 'user' => $doc['idUser']];
                }
            if ($photos)
                foreach ($photos as $photo) {
                    $photosArr[] = ['file' => $photo['titreFichier']];
                }
            $sign->setDocuments($docsArr);
            $sign->setPhotos($photosArr);
            $entityManager->persist($sign);
            //END DOC & PHOTOS

            $suivisQuery = "SELECT * from hsuivi h  WHERE h.idSignalement= '" . $signalement['idSignalement'] . "'";
            $suivis = $conn->query($suivisQuery)->fetch_all(MYSQLI_ASSOC);
            if ($suivis) {
                foreach ($suivis as $suivi) {
                    $partenaire = $entityManager->getRepository(Partenaire::class)->find($suivi['idPartenaire']);
                    if ($partenaire) {
                        $createdBy = $partenaire->getUsers()->first();
                        if ($createdBy) {
                            if ($suivi['avisUser'] === "off")
                                $isPublic = 0;
                            else
                                $isPublic = 1;
                            $s = new Suivi();
                            $s->setCreatedAt(new \DateTimeImmutable($suivi['dtSuivi']));
                            $s->setSignalement($sign);
                            $s->setDescription($suivi['descSuivi']);
                            $s->setIsPublic($isPublic);
                            $s->setCreatedBy($createdBy);
                            $entityManager->persist($s);
                        }
                    }
                }
            }
            $entityManager->flush();
            $affectationsQuery = "SELECT * from hsign_hpart aff WHERE aff.idSignalement= '" . $signalement['idSignalement'] . "'";
            $affectations = $conn->query($affectationsQuery)->fetch_all(MYSQLI_ASSOC);
            if ($affectations) {
                $entityManager->persist($sign);
                foreach ($affectations as $affectation) {
                    $user = $entityManager->getRepository(User::class)->find($affectation['idUserBO']);
                    if ($affectation['affectBy'] !== null)
                        $affectedBy = $entityManager->getRepository(User::class)->find($affectation['affectBy']);
                    if ($user) {
                        if ($affectation['etat'] === "8" || $affectation['etat'] === "4") {
                            $statut = Affectation::STATUS_CLOSED;
                        } else {
                            $statut = match ($affectation['affect']) {
                                "0", "1" => Affectation::STATUS_WAIT,
                                "2" => Affectation::STATUS_ACCEPTED,
                                "3" => Affectation::STATUS_REFUSED,
                            };
                        }
                        if (!isset($affectedBy))
                            $affectedBy = $user->getPartenaire()->getUsers()->first();
                        if (!$affectationRepository->findBy(['partenaire' => $user->getPartenaire(), 'signalement' => $sign]) && isset($affectedBy) && $user->getPartenaire()) {
                            $a = new Affectation();
                            $a->setPartenaire($user->getPartenaire());
                            $a->setSignalement($sign);
                            $a->setStatut($statut);
                            $a->setAnsweredBy($user);
                            $a->setAnsweredAt(new \DateTimeImmutable($affectation['dtAffect']));
                            if ($sign->getStatut() === Signalement::STATUS_CLOSED) {
                                $sign->setClosedAt(new \DateTimeImmutable($affectation['dtAffect']));
                                $entityManager->persist($sign);
                            }
                            $a->setAffectedBy($affectedBy);
                            $a->setCreatedAt(new \DateTimeImmutable($affectation['dtAlert'] ?? $affectation['dtAffect']));
                            $entityManager->persist($a);
                            $entityManager->flush();
                        }
                    }
                }
            }
            //END AFFECTATION
            $count++;
            /* if (($offset + $count) === 6146)
                 dd('finish');*/
        }
        die('Finish');
        return $this->redirectToRoute('dump', ['offset' => $offset + $count]);
    }


    #[Route('/', name: 'home')]
    public function index(SignalementRepository $signalementRepository, AffectationRepository $affectationRepository, EntityManagerInterface $doctrine, NotificationService $notificationService): Response
    {
        $title = 'Un service public pour les locataires et propriétaires';
        $year = (new \DateTimeImmutable())->format('Y');
        $total = $signalementRepository->findAllWithAffectations($year);
        $stats['total'] = count($total);
        $stats['pec'] = $stats['total'] !== 0 ? floor(($affectationRepository->createQueryBuilder('a')->select('COUNT(DISTINCT a.signalement)')->join('a.signalement', 'signalement', 'WITH', 'signalement.statut != 7 AND YEAR(signalement.createdAt) = ' . $year)->getQuery()->getSingleScalarResult() / $stats['total']) * 100) : 0;
        $stats['res'] = $stats['total'] !== 0 ? floor(($affectationRepository->createQueryBuilder('a')->select('COUNT(DISTINCT a.signalement)')->where('a.statut = 1')->join('a.signalement', 'signalement', 'WITH', 'signalement.statut != 7 AND YEAR(signalement.createdAt) = ' . $year)->getQuery()->getSingleScalarResult() / $stats['total']) * 100) : 0;
        return $this->render('front/index.html.twig', [
            'title' => $title,
            'stats' => $stats
        ]);
    }

    #[Route('/qui-sommes-nous', name: 'front_about')]
    public function about(): Response
    {
        $title = 'Qui sommes-nous ?';
        return $this->render('front/about.html.twig', [
            'title' => $title
        ]);
    }

    #[Route('/contact', name: 'front_contact')]
    public function contact(Request $request, NotificationService $notificationService, ConfigurationService $configurationService): Response
    {
        $title = "Conditions Générales d'Utilisation";
        $form = $this->createForm(ContactType::class, []);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $notificationService->send(NotificationService::TYPE_NOTIFICATION_MAIL_FRONT, ['notifications@histologe.fr', $configurationService->get()->getEmailReponse()], [
                'nom' => $form->get('nom')->getData(),
                'mail' => $form->get('email')->getData(),
                'reply' => $form->get('email')->getData(),
                'message' => nl2br($form->get('message')->getData()),
            ]);
            $this->addFlash('success', 'Votre message à bien été envoyé !');
            return $this->redirectToRoute('front_contact');
        }
        return $this->render('front/contact.html.twig', [
            'title' => $title,
            'form' => $form->createView()
        ]);
    }

    #[Route('/cgu', name: 'front_cgu')]
    public function cgu(): Response
    {
        $title = "Conditions Générales d'Utilisation";
        return $this->render('front/cgu.html.twig', [
            'title' => $title
        ]);
    }
}
