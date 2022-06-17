<?php

namespace App\Service;

use App\Entity\Affectation;
use App\Entity\Signalement;
use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

class AffectationCheckerService
{
    public function check(Signalement $signalement,User $currentUser)
    {
        $affectationCurrentUser = $signalement->getAffectations()->filter(function (Affectation $affectation)use ($currentUser) {
            if ($affectation->getPartenaire()->getId() === $currentUser->getPartenaire()->getId())
                return $affectation;
        });
        if ($affectationCurrentUser->isEmpty())
            return false;
        return $affectationCurrentUser->first();
    }

    public function checkIfSignalementClosedForUser(User|UserInterface $user,Signalement $signalement){
        if ($user->getPartenaire()) {
            $clotureCurrentUser = $signalement->getAffectations()->filter(function (Affectation $affectation)use($user) {
                if ($affectation->getPartenaire()->getId() === $user->getPartenaire()->getId() && ($affectation->getStatut() === Affectation::STATUS_CLOSED || $affectation->getStatut() === Affectation::STATUS_REFUSED))
                    return $affectation;
            });
            if (!$clotureCurrentUser->isEmpty())
                return $clotureCurrentUser->first();
        }
        return false;
    }

}