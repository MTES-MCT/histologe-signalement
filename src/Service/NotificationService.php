<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class NotificationService
{
    const TYPE_ACTIVATION = 1;
    const TYPE_RAPPEL_ACTIVATION = 11;
    const TYPE_LOST_PASSWORD = 2;
    const TYPE_NEW_SIGNALEMENT = 3;
    const TYPE_AFFECTATION = 4;
    const TYPE_SIGNALEMENT_VALIDE = 5;
    const TYPE_SIGNALEMENT_REFUSE = 99;
    const TYPE_ACCUSE_RECEPTION = 6;
    const TYPE_NOUVEAU_SUIVI = 7;
    const TYPE_NOUVEAU_SUIVI_BACK = 10;
    const TYPE_NOTIFICATION_MAIL_FRONT = 8;
    const TYPE_ERREUR_SIGNALEMENT = 9;

    private MailerInterface $mailer;
    private ConfigurationService $configuration;

    public function __construct(MailerInterface $mailer, ConfigurationService $configurationService, EntityManagerInterface $entityManager)
    {
        $this->mailer = $mailer;
        $this->configuration = $configurationService;
        $this->em = $entityManager;
    }

    private function config(int $type): array
    {
        return match ($type) {
            NotificationService::TYPE_ACTIVATION => [
                'template' => 'login_link_email',
                'subject' => 'Activation de votre compte',
                'btntext' => "J'active mon compte"
            ],
            NotificationService::TYPE_RAPPEL_ACTIVATION => [
                'template' => 'login_link_email',
                'subject' => 'Vous n\'avez toujours pas activer votre compte',
                'btntext' => "J'active mon compte"
            ],
            NotificationService::TYPE_LOST_PASSWORD => [
                'template' => 'lost_pass_email',
                'subject' => 'Récupération de votre mot de passe',
                'btntext' => "Je créer un nouveau mot de passe"
            ],
            NotificationService::TYPE_NEW_SIGNALEMENT => [
                'template' => 'new_signalement_email',
                'subject' => 'Un nouveau signalement vous attend',
                'btntext' => "Voir le signalement"
            ],
            NotificationService::TYPE_AFFECTATION => [
                'template' => 'affectation_email',
                'subject' => 'Vous avez été affecté à un signalement',
                'btntext' => "Voir le signalement"
            ],
            NotificationService::TYPE_SIGNALEMENT_VALIDE => [
                'template' => 'validation_signalement_email',
                'subject' => 'Votre signalement est validé !',
                'btntext' => "Suivre mon signalement"
            ],
            NotificationService::TYPE_SIGNALEMENT_REFUSE => [
                'template' => 'refus_signalement_email',
                'subject' => 'Votre signalement ne peut pas être traité.',
            ],
            NotificationService::TYPE_NOTIFICATION_MAIL_FRONT => [
                'template' => 'nouveau_mail_front',
                'subject' => 'Vous avez reçu un message depuis la page Histologe',
            ],
            NotificationService::TYPE_ACCUSE_RECEPTION => [
                'template' => 'accuse_reception_email',
                'subject' => 'Votre signalement a bien été reçu !',
            ],
            NotificationService::TYPE_NOUVEAU_SUIVI => [
                'template' => 'nouveau_suivi_signalement_email',
                'subject' => 'Nouvelle mise à jour de votre signalement !',
                'btntext' => "Suivre mon signalement"
            ],
            NotificationService::TYPE_NOUVEAU_SUIVI_BACK => [
                'template' => 'nouveau_suivi_signalement_back_email',
                'subject' => 'Nouveau suivi sur un signalement',
                'btntext' => "Consulter sur la plateforme"
            ],
            NotificationService::TYPE_ERREUR_SIGNALEMENT => [
                'template' => 'erreur_signalement_email',
                'subject' => 'Une erreur est survenue lors de la création d\'un signalement !',
            ]
        };
    }

    public function send(int $type, string|array $to, array $params): TransportExceptionInterface|\Exception|bool
    {
        $params['url'] = $_SERVER['SERVER_NAME'] ?? null;
        $message = $this->renderMailContentWithParamsByType($type, $params);
        is_array($to) ? $emails = $to : $emails = [$to];
        foreach ($emails as $email)
            $email && $message->addTo($email);
        $message->from(new Address('histologe-'.str_replace(' ','-',mb_strtolower($this->configuration->get()->getNomTerritoire())).'@histologe.fr','HISTOLOGE - '.mb_strtoupper($this->configuration->get()->getNomTerritoire())));
        if (!empty($params['attach']))
            $message->attachFromPath($params['attach']);
        if ($this->configuration->get()->getEmailReponse() ?? isset($params['reply']))
            $message->replyTo($params['reply'] ?? $this->configuration->get()->getEmailReponse());
        try {
            $this->mailer->send($message);
            return true;
        } catch (TransportExceptionInterface $e) {
            return $e;
        }
    }

    private function renderMailContentWithParamsByType(int $type, array $params): NotificationEmail
    {

        $config = $this->config($type);
        $notification = new NotificationEmail();
        $notification->markAsPublic();
        return $notification->htmlTemplate('emails/' . $config['template'] . '.html.twig')
            ->context(array_merge($params, $config))
            ->subject('HISTOLOGE ' . mb_strtoupper($this->configuration->get()->getNomTerritoire()) . ' - ' . $config['subject']);
    }
}