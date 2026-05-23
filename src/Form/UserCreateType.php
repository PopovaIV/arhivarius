<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Логин',
                'help' => 'Латиница, цифры, . _ -',
            ])
            ->add('displayName', TextType::class, [
                'label' => 'Отображаемое имя',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Пароль'],
                'second_options' => ['label' => 'Повторите пароль'],
                'invalid_message' => 'Пароли не совпадают',
                'constraints' => [
                    new NotBlank(message: 'Введите пароль'),
                    new Length(min: 8, minMessage: 'Минимум {{ limit }} символов'),
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Роль',
                'mapped' => false,
                'choices' => [
                    'Соавтор (может добавлять и удалять своё)' => User::ROLE_CONTRIBUTOR,
                    'Только просмотр' => User::ROLE_VIEWER,
                    'Администратор' => User::ROLE_ADMIN,
                ],
                'data' => User::ROLE_CONTRIBUTOR,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Активен',
                'required' => false,
                'data' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
