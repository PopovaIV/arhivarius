<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Photo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class PhotoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Название'])
            ->add('description', TextareaType::class, [
                'label' => 'Описание', 'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('takenDate', TextType::class, [
                'label' => 'Дата съёмки', 'required' => false,
                'help' => 'Свободный формат: «лето 1912», «около 1900»',
            ])
            ->add('takenYear', IntegerType::class, [
                'label' => 'Год (для сортировки)', 'required' => false,
                'attr' => ['min' => 1800, 'max' => 2100],
            ])
            ->add('place', TextType::class, [
                'label' => 'Место съёмки', 'required' => false,
            ]);

        if ($options['require_file']) {
            $builder->add('imageFile', FileType::class, [
                'label' => 'Файл фотографии',
                'mapped' => false,
                'required' => true,
                'help' => 'JPG, PNG, WEBP. До 50 МБ.',
                'constraints' => [
                    new File(
                        maxSize: '50M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'JPG, PNG или WEBP',
                    ),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Photo::class,
            'require_file' => true,
        ]);
        $resolver->setAllowedTypes('require_file', 'bool');
    }
}
