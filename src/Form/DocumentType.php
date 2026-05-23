<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Document;
use App\Enum\DatePrecision;
use App\Enum\DocumentCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

final class DocumentType extends AbstractType
{
    public function __construct(private readonly MetadataFormResolver $resolver) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var DocumentCategory $category */
        $category = $options['category'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Название',
                'help' => 'Краткое название документа',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание / комментарий',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('documentDate', TextType::class, [
                'label' => 'Дата документа',
                'required' => false,
                'help' => 'Свободный формат: «12 апреля 1850», «1850-е», «около 1810»',
            ])
            ->add('documentYear', IntegerType::class, [
                'label' => 'Год (для сортировки)',
                'required' => false,
                'attr' => ['min' => 1500, 'max' => 2100],
            ])
            ->add('datePrecision', EnumType::class, [
                'class' => DatePrecision::class,
                'choice_label' => fn (DatePrecision $p) => $p->label(),
                'label' => 'Точность даты',
            ])
            ->add('place', TextType::class, [
                'label' => 'Место',
                'required' => false,
                'help' => 'Приход, уезд, губерния, страна',
            ])
            ->add('archiveReference', TextType::class, [
                'label' => 'Архивный шифр',
                'required' => false,
                'help' => 'Например: ЦГИА СПб, ф. 19, оп. 124, д. 567, л. 12 об.',
            ]);

        // Категория-специфичные поля
        $metaFormClass = $this->resolver->formClassFor($category);
        if ($metaFormClass !== null) {
            $builder->add('metadata', $metaFormClass, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'data' => $options['metadata_data'] ?? [],
            ]);
        }

        $builder->add('uploadFiles', FileType::class, [
            'label' => 'Сканы / файлы документа',
            'multiple' => true,
            'mapped' => false,
            'required' => false,
            'help' => 'PDF, JPG, PNG, TIFF, DJVU. До 500 МБ на файл.',
            'constraints' => [
                new All([
                    new File(
                        maxSize: '500M',
                        mimeTypes: [
                            'application/pdf',
                            'image/jpeg', 'image/png', 'image/tiff', 'image/webp',
                            'image/vnd.djvu', 'image/x.djvu',
                        ],
                        mimeTypesMessage: 'Поддерживаются PDF, JPG, PNG, TIFF, WEBP, DJVU',
                    ),
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'metadata_data' => [],
        ]);
        $resolver->setRequired('category');
        $resolver->setAllowedTypes('category', DocumentCategory::class);
        $resolver->setAllowedTypes('metadata_data', 'array');
    }
}
