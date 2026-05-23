<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ExternalLink;
use App\Enum\LinkCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExternalLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EnumType::class, [
                'class' => LinkCategory::class,
                'choice_label' => fn (LinkCategory $c) => $c->label(),
                'label' => 'Категория',
            ])
            ->add('title', TextType::class, ['label' => 'Название'])
            ->add('url', UrlType::class, ['label' => 'URL'])
            ->add('description', TextareaType::class, [
                'label' => 'Описание', 'required' => false,
                'attr' => ['rows' => 3],
                'help' => 'Зачем эта ссылка пригодится, что там найти',
            ])
            ->add('tags', TextType::class, [
                'label' => 'Теги', 'required' => false,
                'help' => 'Через запятую, например: «Подольская губерния, духовные книги»',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ExternalLink::class]);
    }
}
