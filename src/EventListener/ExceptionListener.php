<?php

namespace App\EventListener;

use App\Service\NotificationService;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use function PHPUnit\Framework\isEmpty;

class ExceptionListener
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        if ($event->getRequest()->get('signalement') !== null) {
            $attachment =['documents'=>0,'photos'=>0];
            if($files = $event->getRequest()->files->get('signalement')) {
                foreach ($files as $k=> $file) {
                    foreach ($file as $file_) {
                        $attachment[$k]++;
                    }
                }
            }
            $this->notificationService->send(NotificationService::TYPE_ERREUR_SIGNALEMENT, 'sam@bzez.dev', [
                'url' => $_SERVER['SERVER_NAME'],
                'code' => $event->getThrowable()->getCode(),
                'error' => $event->getThrowable()->getMessage(),
                'req' => $event->getRequest(),
                'signalement' => $event->getRequest()->get('signalement'),
                'attachment' => $attachment
            ]);
        }
    }
}