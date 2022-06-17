<?php

namespace App\Form;

use App\Entity\Signalement;
use App\Entity\Situation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SignalementSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('details')
            ->add('photos')
            ->add('documents')
            ->add('isProprioAverti')
            ->add('nbAdultes')
            ->add('nbEnfantsM6')
            ->add('nbEnfantsP6')
            ->add('isAllocataire')
            ->add('numAllocataire')
            ->add('natureLogement')
            ->add('typeLogement')
            ->add('superficie')
            ->add('loyer')
            ->add('isBailEnCours')
            ->add('dateEntree')
            ->add('nomProprio')
            ->add('adresseProprio')
            ->add('telProprio')
            ->add('mailProprio')
            ->add('isLogementSocial')
            ->add('isPreavisDepart')
            ->add('isRelogement')
            ->add('isRefusIntervention')
            ->add('raisonRefusIntervention')
            ->add('isNotOccupant')
            ->add('nomDeclarant')
            ->add('prenomDeclarant')
            ->add('telDeclarant')
            ->add('mailDeclarant')
            ->add('structureDeclarant')
            ->add('nomOccupant')
            ->add('prenomOccupant')
            ->add('telOccupant')
            ->add('mailOccupant')
            ->add('adresseOccupant')
            ->add('cpOccupant')
            ->add('villeOccupant')
            ->add('isCguAccepted')
            ->add('createdAt')
            ->add('modifiedAt')
            ->add('statut')
            ->add('reference')
            ->add('jsonContent')
            ->add('geoloc')
            ->add('dateVisite')
            ->add('isOccupantPresentVisite')
            ->add('montantAllocation')
            ->add('isSituationHandicap')
            ->add('codeProcedure')
            ->add('scoreCreation')
            ->add('scoreCloture')
            ->add('etageOccupant')
            ->add('escalierOccupant')
            ->add('numAppartOccupant')
            ->add('adresseAutreOccupant')
            ->add('modeContactProprio')
            ->add('inseeOccupant')
            ->add('codeSuivi')
            ->add('lienDeclarantOccupant')
            ->add('isConsentementTiers')
            ->add('validatedAt')
            ->add('isRsa')
            ->add('prorioAvertiAt')
            ->add('anneeConstruction')
            ->add('typeEnergieLogement')
            ->add('origineSignalement')
            ->add('situationOccupant')
            ->add('situationProOccupant')
            ->add('naissanceOccupantAt')
            ->add('isLogementCollectif')
            ->add('isConstructionAvant1949')
            ->add('isDiagSocioTechnique')
            ->add('isFondSolidariteLogement')
            ->add('isRisqueSurOccupation')
            ->add('proprioAvertiAt')
            ->add('nomReferentSocial')
            ->add('StructureReferentSocial')
            ->add('mailSyndic')
            ->add('nomSci')
            ->add('nomRepresentantSci')
            ->add('telSci')
            ->add('mailSci')
            ->add('telSyndic')
            ->add('nomSyndic')
            ->add('numeroInvariant')
            ->add('nbPiecesLogement')
            ->add('nbChambresLogement')
            ->add('nbNiveauxLogement')
            ->add('nbOccupantsLogement')
            ->add('motifCloture')
            ->add('closedAt')
            ->add('telOccupantBis')
            ->add('situations',EntityType::class,[
                'class'=> Situation::class,
                'choice_label'=> 'label'
            ])
            ->add('criteres',EntityType::class,[
                'class'=> Situation::class,
                'choice_label'=> 'label'
            ])
            ->add('criticites',EntityType::class,[
                'class'=> Situation::class,
                'choice_label'=> 'label'
            ])
//            ->add('modifiedBy')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Signalement::class,
        ]);
    }
}
