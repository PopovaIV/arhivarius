<?php

declare(strict_types=1);

namespace App\Form\CategoryMetadata;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ShipLogMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ship_name', TextType::class, [
                'label' => 'Название судна', 'required' => false,
            ])
            ->add('ship_type', TextType::class, [
                'label' => 'Тип судна', 'required' => false,
                'help' => 'Пароход, парусник, пакетбот и т.п.',
            ])
            ->add('captain', TextType::class, [
                'label' => 'Капитан', 'required' => false,
            ])
            ->add('voyage_date', TextType::class, [
                'label' => 'Дата рейса', 'required' => false,
            ])
            ->add('port_departure', TextType::class, [
                'label' => 'Порт отправления', 'required' => false,
            ])
            ->add('port_arrival', TextType::class, [
                'label' => 'Порт прибытия', 'required' => false,
            ])
            ->add('passengers', TextareaType::class, [
                'label' => 'Пассажиры',
                'required' => false,
                'help' => 'По одному пассажиру в строку. Например: «Иван Петров, 32, мещанин Гродно, класс II»',
                'attr' => ['rows' => 10],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Заметки журнала',
                'required' => false,
                'help' => 'Происшествия, остановки, прочие отметки',
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'empty_data' => [], 'allow_extra_fields' => true]);
    }
}
