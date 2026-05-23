<?php

declare(strict_types=1);

namespace App\Form\CategoryMetadata;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ConfessionListMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('parish_name', TextType::class, [
                'label' => 'Приход',
                'required' => false,
                'help' => 'Название церкви и местоположение',
            ])
            ->add('priest', TextType::class, [
                'label' => 'Священник', 'required' => false,
            ])
            ->add('estate_type', TextType::class, [
                'label' => 'Сословие / категория', 'required' => false,
                'help' => 'Военные, духовные, мещане, крестьяне и т.д.',
            ])
            ->add('household_head', TextType::class, [
                'label' => 'Глава семьи', 'required' => false,
                'help' => 'ФИО, возраст',
            ])
            ->add('household_members', TextareaType::class, [
                'label' => 'Члены семьи',
                'required' => false,
                'help' => 'По одному человеку в строку: «Имя, возраст, родственная связь, был на исповеди»',
                'attr' => ['rows' => 8],
            ])
            ->add('not_confessed_reason', TextType::class, [
                'label' => 'Если кто-то не исповедовался — причина',
                'required' => false,
                'help' => 'Малолетство, болезнь, нерадение и т.п.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'empty_data' => [], 'allow_extra_fields' => true]);
    }
}
