<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Person;
use App\Enum\Gender;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PersonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Полное имя',
                'help' => 'Имя, отчество, фамилия',
            ])
            ->add('aliases', TextType::class, [
                'label' => 'Девичья фамилия / прозвища',
                'required' => false,
                'help' => 'Помогает находить человека при поиске',
            ])
            ->add('gender', EnumType::class, [
                'class' => Gender::class,
                'choice_label' => fn (Gender $g) => $g->label(),
                'label' => 'Пол',
            ])
            ->add('birthDate', TextType::class, [
                'label' => 'Дата рождения', 'required' => false,
                'help' => 'Свободный формат',
            ])
            ->add('birthYear', IntegerType::class, [
                'label' => 'Год рождения', 'required' => false,
                'attr' => ['min' => 1500, 'max' => 2100],
            ])
            ->add('birthPlace', TextType::class, [
                'label' => 'Место рождения', 'required' => false,
            ])
            ->add('deathDate', TextType::class, [
                'label' => 'Дата смерти', 'required' => false,
            ])
            ->add('deathYear', IntegerType::class, [
                'label' => 'Год смерти', 'required' => false,
                'attr' => ['min' => 1500, 'max' => 2100],
            ])
            ->add('deathPlace', TextType::class, [
                'label' => 'Место смерти', 'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Заметки', 'required' => false,
                'attr' => ['rows' => 4],
                'help' => 'Биография, профессия, особенности — что хочется запомнить',
            ])
            ->add('externalTreeUrl', \Symfony\Component\Form\Extension\Core\Type\UrlType::class, [
                'label' => 'Ссылка на внешнее древо',
                'required' => false,
                'help' => 'Familio, MyHeritage, FamilySearch и другие',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Person::class]);
    }
}
