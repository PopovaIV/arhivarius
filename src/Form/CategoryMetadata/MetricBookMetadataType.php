<?php

declare(strict_types=1);

namespace App\Form\CategoryMetadata;

use App\Enum\MetricRecordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Метрические книги — три подтипа записей.
 * Все поля в одной форме, JS показывает блок согласно record_type.
 * Незаполненные поля при сохранении отсеиваются.
 */
final class MetricBookMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('record_type', EnumType::class, [
                'class' => MetricRecordType::class,
                'choice_label' => fn (MetricRecordType $t) => $t->label(),
                'label' => 'Тип записи',
                'placeholder' => '— выберите —',
                'attr' => ['data-metric-record-target' => 'select'],
            ])

            // ===== РОЖДЕНИЕ =====
            ->add('child_name', TextType::class, [
                'label' => 'Имя ребёнка', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'birth'],
            ])
            ->add('child_sex', ChoiceType::class, [
                'label' => 'Пол', 'required' => false,
                'choices' => ['Мужской' => 'male', 'Женский' => 'female'],
                'placeholder' => '— не указан —',
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'birth'],
            ])
            ->add('birth_date_text', TextType::class, [
                'label' => 'Дата рождения', 'required' => false,
                'help' => 'Например: «12 апреля» или «12 апреля 1850»',
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'birth'],
            ])
            ->add('baptism_date', TextType::class, [
                'label' => 'Дата крещения', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'birth'],
            ])
            ->add('father', TextType::class, [
                'label' => 'Отец', 'required' => false,
                'help' => 'Имя, отчество, фамилия, сословие/звание',
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'birth'],
            ])
            ->add('mother', TextType::class, [
                'label' => 'Мать', 'required' => false,
                'help' => 'Имя, отчество (девичья фамилия)',
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'birth'],
            ])
            ->add('legitimate', ChoiceType::class, [
                'label' => 'Законнорождённый', 'required' => false,
                'choices' => ['Да' => 'yes', 'Незаконнорождённый' => 'no'],
                'placeholder' => '— не указано —',
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'birth'],
            ])
            ->add('godparents', TextareaType::class, [
                'label' => 'Восприемники (крёстные)', 'required' => false,
                'help' => 'По одному человеку в строку: «Имя, статус»',
                'attr' => ['rows' => 3],
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'birth'],
            ])

            // ===== БРАК =====
            ->add('groom', TextType::class, [
                'label' => 'Жених', 'required' => false,
                'help' => 'Имя, отчество, фамилия, сословие, место жительства',
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'marriage'],
            ])
            ->add('groom_age', IntegerType::class, [
                'label' => 'Возраст жениха', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'marriage'],
            ])
            ->add('groom_status', TextType::class, [
                'label' => 'Статус жениха', 'required' => false,
                'help' => 'Холост / вдовец / какой брак по счёту',
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'marriage'],
            ])
            ->add('bride', TextType::class, [
                'label' => 'Невеста', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'marriage'],
            ])
            ->add('bride_age', IntegerType::class, [
                'label' => 'Возраст невесты', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'marriage'],
            ])
            ->add('bride_status', TextType::class, [
                'label' => 'Статус невесты', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'marriage'],
            ])
            ->add('wedding_date', TextType::class, [
                'label' => 'Дата венчания', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'marriage'],
            ])
            ->add('witnesses', TextareaType::class, [
                'label' => 'Поручители', 'required' => false,
                'help' => 'По одному человеку в строку',
                'attr' => ['rows' => 3],
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'marriage'],
            ])

            // ===== СМЕРТЬ =====
            ->add('deceased', TextType::class, [
                'label' => 'Умерший', 'required' => false,
                'help' => 'Имя, отчество, фамилия, сословие',
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'death'],
            ])
            ->add('deceased_age', TextType::class, [
                'label' => 'Возраст умершего', 'required' => false,
                'help' => 'Может быть текстом: «5 месяцев», «86 лет»',
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'death'],
            ])
            ->add('death_date_text', TextType::class, [
                'label' => 'Дата смерти', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'death'],
            ])
            ->add('death_cause', TextType::class, [
                'label' => 'Причина смерти', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'death'],
            ])
            ->add('burial_date', TextType::class, [
                'label' => 'Дата погребения', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'death'],
            ])
            ->add('burial_place', TextType::class, [
                'label' => 'Место погребения', 'required' => false,
                'row_attr' => ['class' => 'metric-block', 'data-metric-block' => 'death'],
            ])

            // Общее для всех типов
            ->add('priest', TextType::class, [
                'label' => 'Священник', 'required' => false,
                'help' => 'Кто совершал обряд',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,    // данные — обычный массив
            'empty_data' => [],
            'allow_extra_fields' => true,
        ]);
    }
}
