<?php

declare(strict_types=1);

namespace App\Form\CategoryMetadata;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PressMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('publication_name', TextType::class, [
                'label' => 'Название издания', 'required' => false,
                'help' => 'Газета, журнал, бюллетень',
            ])
            ->add('publication_date', TextType::class, [
                'label' => 'Дата выпуска', 'required' => false,
            ])
            ->add('issue_number', TextType::class, [
                'label' => 'Номер выпуска', 'required' => false,
            ])
            ->add('page_number', TextType::class, [
                'label' => 'Страница', 'required' => false,
            ])
            ->add('author', TextType::class, [
                'label' => 'Автор', 'required' => false,
            ])
            ->add('mentioned_persons', TextareaType::class, [
                'label' => 'Упомянутые лица',
                'required' => false,
                'help' => 'По одному имени в строку',
                'attr' => ['rows' => 3],
            ])
            ->add('topics', TextType::class, [
                'label' => 'Темы / теги', 'required' => false,
                'help' => 'Через запятую',
            ])
            ->add('summary', TextareaType::class, [
                'label' => 'Краткое содержание', 'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'empty_data' => [], 'allow_extra_fields' => true]);
    }
}
