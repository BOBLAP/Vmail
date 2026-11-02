<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class RecipientGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'recipient_groups.name',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
                'attr' => ['maxlength' => 100],
            ])
            ->add('addresses', TextareaType::class, [
                'label' => 'recipient_groups.addresses',
                'help' => 'recipient_groups.help',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100000)],
                'attr' => ['rows' => 6, 'maxlength' => 100000],
            ]);
    }
}
