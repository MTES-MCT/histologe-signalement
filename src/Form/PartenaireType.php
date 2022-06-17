<?php

namespace App\Form;

use App\Entity\Partenaire;
use SebastianBergmann\CodeCoverage\Report\Text;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartenaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'attr' => [
                    'class' => 'fr-input'
                ]
            ])
            ->add('isCommune', ChoiceType::class, [
                'row_attr' => [
                    'class' => 'fr-select-group'
                ], 'attr' => [
                    'class' => 'fr-select'
                ],
                'choices' => [
                    'Commune' => 1,
                    'Partenaire' => 0
                ],
                'label_attr' => [
                    'class' => 'fr-label'
                ],
                'label' => 'Type de partenaire'
            ])
            ->add('insee', TextType::class, [
                'attr' => [
                    'class' => 'fr-input'
                ],
                'required'=>false,
            ])
            ->add('email', EmailType::class, [
                'attr' => [
                    'class' => 'fr-input'
                ],
                'required'=>false,
            ])
            ->add('esaboraUrl', UrlType::class, [
                'attr' => [
                    'class' => 'fr-input'
                ],
                'required'=>false,
            ])
            ->add('esaboraToken', TextType::class, [
                'attr' => [
                    'class' => 'fr-input'
                ],
                'required'=>false,
            ]);
            $builder->get('insee')->addModelTransformer(new CallbackTransformer(
                function ($tagsAsArray) {
                    // transform the array to a string
                    return implode(',', $tagsAsArray);
                },
                function ($tagsAsString) {
                    // transform the string back to an array
                    return explode(',', $tagsAsString);
                }
            ));
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenaire::class,
            'allow_extra_fields' => true
        ]);
    }
    
}
