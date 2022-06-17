<?php

namespace App\EventListener;

use App\Entity\Affectation;
use App\Entity\Notification;
use App\Entity\Partenaire;
use App\Entity\Signalement;
use App\Entity\Suivi;
use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ActivityListener implements EventSubscriberInterface
{
    private NotificationService $notifier;
    private UrlGeneratorInterface $urlGenerator;

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
            Events::preRemove,
        ];
    }

    public function __construct(NotificationService $notificationService, UrlGeneratorInterface $urlGenerator)
    {
        $this->notifier = $notificationService;
        $this->urlGenerator = $urlGenerator;
        $this->tos = new ArrayCollection();
        $this->uow = null;
        $this->em = null;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->em = $args->getEntityManager();
        $this->uow = $this->em->getUnitOfWork();
        foreach ($this->uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Signalement) {
                $this->notifyAdmins($entity, Notification::TYPE_NEW_SIGNALEMENT);
                $this->sendMail($entity, NotificationService::TYPE_NEW_SIGNALEMENT);
            } elseif ($entity instanceof Affectation) {
                $partenaire = $entity->getPartenaire();
                $this->notifyPartner($partenaire, $entity, Notification::TYPE_AFFECTATION);
                $this->sendMail($entity, NotificationService::TYPE_AFFECTATION);
            } elseif ($entity instanceof Suivi) {
                $this->notifyAdmins($entity, Notification::TYPE_SUIVI);
                $entity->getSignalement()->getAffectations()->filter(function (Affectation $affectation) use ($entity) {
                    $partenaire = $affectation->getPartenaire();
                    $this->notifyPartner($partenaire, $entity, Notification::TYPE_SUIVI);
                });
                $this->sendMail($entity, NotificationService::TYPE_NOUVEAU_SUIVI_BACK);
                if ($entity->getIsPublic()) {
                    $this->notifier->send(NotificationService::TYPE_NOUVEAU_SUIVI, [$entity->getSignalement()->getMailDeclarant(), $entity->getSignalement()->getMailOccupant()], [
                        'signalement' => $entity->getSignalement(),
                        'lien_suivi' => $this->urlGenerator->generate('front_suivi_signalement', ['code' => $entity->getSignalement()->getCodeSuivi()], 0)
                    ]);
                }
            }

        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if ($entity instanceof Affectation) {
            $entity->getNotifications()->filter(function (Notification $notification) use ($args) {
                $args->getObjectManager()->remove($notification);
            });
            $args->getObjectManager()->flush();
        }
    }

    private function notifyAdmins($entity, $inAppType)
    {
        $admins = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->andWhere('u.statut = 1')
            ->andWhere('JSON_CONTAINS(u.roles, :role) = 1 OR JSON_CONTAINS(u.roles, :role2) = 1')
            ->setParameter('role', '"ROLE_ADMIN"')
            ->setParameter('role2', '"ROLE_ADMIN_TERRITOIRE"')
            ->getQuery()->getResult();
        foreach ($admins as $admin) {
            $this->createInAppNotification($admin, $entity, $inAppType);
            if ($admin->getIsMailingActive())
                $this->tos[] = $admin->getEmail();
        }
    }

    private function notifyPartner($partner, $entity, $inAppType)
    {
        if ($partner->getEmail()) {
            $this->tos->add($partner->getEmail());
        }
        $partner->getUsers()->filter(function (User $user) use ($inAppType, $entity) {
            if ($user->getStatut() === User::STATUS_ACTIVE && $user->getRoles()[0] !== 'ROLE_ADMIN' && $user->getRoles()[0] !== 'ROLE_ADMIN_TERRITOIRE') {
                $this->createInAppNotification($user, $entity, $inAppType);
                if ($user->getIsMailingActive())
                    $this->tos->add($user->getEmail());
            }
        });
    }


    private function sendMail($entity, $mailType, $options = [])
    {
        $options['entity'] = $entity;
        if (!$this->tos->isEmpty()) {
            if ($entity instanceof Signalement)
                $uuid = $entity->getUuid();
            else
                $uuid = $entity->getSignalement()->getUuid();
            $options = array_merge($options, [
                'link' => $this->urlGenerator->generate('back_signalement_view', [
                    'uuid' => $uuid
                ], 0)
            ]);
            $this->notifier->send($mailType, array_unique($this->tos->toArray()), $options);
        }
    }


    private function createInAppNotification($user, $entity, $type)
    {
        $notification = new Notification();
        $notification->setUser($user);
        switch ($type) {
            case Notification::TYPE_SUIVI:
                $notification->setSuivi($entity);
                $notification->setSignalement($entity->getSignalement());
                break;
            case Notification::TYPE_NEW_SIGNALEMENT:
                $notification->setSignalement($entity);
                break;
            default:
                $notification->setAffectation($entity);
                $notification->setSignalement($entity->getSignalement());
                break;
        }
        $notification->setType($type);
        $this->em->persist($notification);
        $this->uow->computeChangeSet(
            $this->em->getClassMetadata(Notification::class),
            $notification
        );
    }

}

