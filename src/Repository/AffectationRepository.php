<?php

namespace App\Repository;

use App\Entity\Affectation;
use App\Entity\Signalement;
use App\Entity\User;
use App\Service\SearchFilterService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Affectation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Affectation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Affectation[]    findAll()
 * @method Affectation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AffectationRepository extends ServiceEntityRepository
{
    const ARRAY_LIST_PAGE_SIZE = 30;
    private SearchFilterService $searchFilterService;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Affectation::class);
        $this->searchFilterService = new SearchFilterService();
    }

    public function countByStatusForUser($user)
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.signalement) as count')
            ->andWhere('a.partenaire = :partenaire')
            ->leftJoin('a.signalement', 's', 'WITH', 's = a.signalement')
            ->andWhere('s.statut != 7')
            ->setParameter('partenaire', $user->getPartenaire())
            ->addSelect('a.statut')
            ->indexBy('a', 'a.statut')
            ->groupBy('a.statut')
            ->getQuery()
            ->getResult();
    }

    public function findByStatusAndOrCityForUser(User|UserInterface $user = null, array $options, $export = null): Paginator|array
    {

        $page = (int)$options['page'];
        $pageSize = $export ?? self::ARRAY_LIST_PAGE_SIZE;
        $firstResult = (($options['page'] ?? 1) - 1) * $pageSize;
        $qb = $this->createQueryBuilder('a');
        $qb->where('s.statut != :status')
            ->setParameter('status', Signalement::STATUS_ARCHIVED);
        if (!$export) {
            $qb->select('a,PARTIAL s.{id,uuid,reference,nomOccupant,prenomOccupant,adresseOccupant,cpOccupant,villeOccupant,scoreCreation,statut,createdAt,geoloc}');
        }
        $qb->leftJoin('a.signalement', 's')
            ->leftJoin('s.tags', 'tags')
            ->leftJoin('s.affectations', 'affectations')
            ->leftJoin('a.partenaire', 'partenaire')
            ->leftJoin('s.suivis', 'suivis')
            ->leftJoin('s.criteres', 'criteres')
            ->addSelect('s', 'partenaire', 'suivis');
        $stat = $statOr = null;
        $qb = $this->searchFilterService->applyFilters($qb, $options);
        if ($options['statuses']) {
            foreach ($options['statuses'] as $k => $statu) {
                if ($statu === (string)Signalement::STATUS_CLOSED) {
                    $options['statuses'][$k] = Affectation::STATUS_CLOSED;
                    $options['statuses'][count($options['statuses'])] = Affectation::STATUS_REFUSED;
                } else if ($statu === (string)Signalement::STATUS_ACTIVE)
                    $options['statuses'][$k] = Affectation::STATUS_ACCEPTED;
                else if ($statu === (string)Signalement::STATUS_NEED_VALIDATION)
                    $options['statuses'][$k] = Affectation::STATUS_WAIT;
            }
            $qb->andWhere('a.statut IN (:statuses)');
            if ($statOr)
                $qb->orWhere('a.statut IN (:statuses)');
            $qb->setParameter('statuses', $options['statuses']);
        }
        if ($user)
            $qb->andWhere(':partenaire IN (partenaire)')
                ->setParameter('partenaire', $user->getPartenaire());

        $qb->orderBy('s.createdAt', 'DESC');
        if (!$export) {
            $qb->setFirstResult($firstResult)
                ->setMaxResults($pageSize)
                ->getQuery();

            return new Paginator($qb, true);
        }
        return $qb->getQuery()->getResult();
    }

}
