<?php

declare(strict_types=1);

namespace App\Form\CategoryMetadata;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EmigrationMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('document_subtype', ChoiceType::class, [
                'label' => 'Тип документа',
                'required' => false,
                'choices' => [
                    'Заграничный паспорт' => 'foreign_passport',
                    'Прошение о выезде' => 'exit_petition',
                    'Разрешение на эмиграцию' => 'emigration_permit',
                    'Корабельный билет' => 'ship_ticket',
                    'Декларация о натурализации' => 'naturalization',
                    'Другое' => 'other',
                ],
                'placeholder' => '— не выбрано —',
            ])
            ->add('person_name', TextType::class, [
                'label' => 'Имя эмигранта', 'required' => false,
            ])
            ->add('age', TextType::class, [
                'label' => 'Возраст / год рождения', 'required' => false,
            ])
            ->add('origin', TextType::class, [
                'label' => 'Откуда (место рождения / жительства)', 'required' => false,
            ])
            ->add('destination_country', TextType::class, [
                'label' => 'Страна назначения', 'required' => false,
            ])
            ->add('destination_city', TextType::class, [
                'label' => 'Город / штат назначения', 'required' => false,
            ])
            ->add('emigration_date', TextType::class, [
                'label' => 'Дата выезда', 'required' => false,
            ])
            ->add('port_departure', TextType::class, [
                'label' => 'Порт отправления', 'required' => false,
            ])
            ->add('port_arrival', TextType::class, [
                'label' => 'Порт прибытия', 'required' => false,
            ])
            ->add('ship_name', TextType::class, [
                'label' => 'Название судна', 'required' => false,
            ])
            ->add('family_members', TextareaType::class, [
                'label' => 'Сопровождающие',
                'required' => false,
                'help' => 'По одному человеку в строку: «Имя, родственная связь, возраст»',
                'attr' => ['rows' => 4],
            ])
            ->add('reason', TextType::class, [
                'label' => 'Причина выезда (если указана)', 'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'empty_data' => [], 'allow_extra_fields' => true]);
    }
}
