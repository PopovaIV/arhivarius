<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Book;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

final class BookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Название'])
            ->add('authors', TextType::class, [
                'label' => 'Авторы', 'required' => false,
                'help' => 'Можно несколько через запятую',
            ])
            ->add('year', IntegerType::class, [
                'label' => 'Год издания', 'required' => false,
                'attr' => ['min' => 1400, 'max' => 2100],
            ])
            ->add('publisher', TextType::class, ['label' => 'Издатель', 'required' => false])
            ->add('language', ChoiceType::class, [
                'label' => 'Язык', 'required' => false,
                'choices' => [
                    'Русский' => 'ru', 'Английский' => 'en', 'Немецкий' => 'de',
                    'Французский' => 'fr', 'Польский' => 'pl', 'Идиш' => 'yi',
                    'Иврит' => 'he', 'Латынь' => 'la', 'Старославянский' => 'cu',
                    'Другой' => 'other',
                ],
                'placeholder' => '— не указан —',
            ])
            ->add('genre', TextType::class, [
                'label' => 'Жанр / тематика', 'required' => false,
                'help' => 'Например: «справочник», «мемуары», «генеалогия»',
            ])
            ->add('isbn', TextType::class, ['label' => 'ISBN', 'required' => false])
            ->add('description', TextareaType::class, [
                'label' => 'Описание', 'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('uploadFiles', FileType::class, [
                'label' => 'Файлы книги',
                'multiple' => true,
                'mapped' => false,
                'required' => $options['files_required'],
                'help' => 'Можно несколько форматов одной книги (PDF, EPUB, DJVU, FB2, MOBI, TXT). До 500 МБ на файл.',
                'constraints' => [
                    new All([
                        new File(
                            maxSize: '500M',
                            extensions: ['pdf', 'epub', 'djvu', 'djv', 'fb2', 'mobi', 'txt'],
                            extensionsMessage: 'Поддерживаются PDF, EPUB, DJVU, FB2, MOBI, TXT',
                        ),
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
            'files_required' => false,
        ]);
        $resolver->setAllowedTypes('files_required', 'bool');
    }
}
