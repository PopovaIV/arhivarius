<?php

declare(strict_types=1);

namespace App\Form\CategoryMetadata;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RevisionTaleMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('revision_number', ChoiceType::class, [
                'label' => 'Номер ревизии',
                'choices' => [
                    'I ревизия (1719)'   => 1,
                    'II ревизия (1744)'  => 2,
                    'III ревизия (1762)' => 3,
                    'IV ревизия (1782)'  => 4,
                    'V ревизия (1795)'   => 5,
                    'VI ревизия (1811)'  => 6,
                    'VII ревизия (1816)' => 7,
                    'VIII ревизия (1834)'=> 8,
                    'IX ревизия (1850)'  => 9,
                    'X ревизия (1858)'   => 10,
                ],
                'placeholder' => '— выберите —',
                'required' => false,
            ])
            ->add('province', TextType::class, [
                'label' => 'Губерния', 'required' => false,
            ])
            ->add('district', TextType::class, [
                'label' => 'Уезд', 'required' => false,
            ])
            ->add('settlement', TextType::class, [
                'label' => 'Селение / город', 'required' => false,
            ])
            ->add('estate_type', TextType::class, [
                'label' => 'Сословие / категория',
                'required' => false,
                'help' => 'Помещичьи / государственные / удельные крестьяне, мещане, купцы и т.д.',
            ])
            ->add('landlord', TextType::class, [
                'label' => 'Помещик', 'required' => false,
                'help' => 'Если применимо',
            ])
            ->add('household_head', TextType::class, [
                'label' => 'Глава семьи',
                'required' => false,
                'help' => 'ФИО, возраст',
            ])
            ->add('household_members', TextareaType::class, [
                'label' => 'Состав двора',
                'required' => false,
                'help' => 'По одному человеку в строку. Например: «Сын Иван, 24 года, отдан в рекруты в 1853»',
                'attr' => ['rows' => 8],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'empty_data' => [], 'allow_extra_fields' => true]);
    }
}
