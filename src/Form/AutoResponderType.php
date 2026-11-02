<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AutoResponderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('enabled', CheckboxType::class, ['required' => false, 'label' => 'auto_responder.enabled']);
        foreach (['startsAt' => 'auto_responder.starts', 'endsAt' => 'auto_responder.ends'] as $name => $label) {
            $builder->add($name, DateTimeType::class, [
                'label' => $label, 'widget' => 'single_text', 'input' => 'datetime_immutable',
                'with_seconds' => true, 'model_timezone' => 'UTC', 'view_timezone' => $options['timezone'],
                'attr' => ['step' => 1], 'constraints' => [new Assert\NotNull()],
            ]);
        }
        $builder->add('format', ChoiceType::class, ['label' => 'auto_responder.format', 'choices' => [
            'auto_responder.plain' => 'text', 'auto_responder.html' => 'html',
        ]]);
        $builder->add('subject', TextType::class, ['required' => false, 'label' => 'auto_responder.subject', 'constraints' => [new Assert\Length(max: 200), new Assert\Regex(pattern: '/^[^\r\n]*$/')], 'attr' => ['maxlength' => 200]]);
        $builder->add('message', TextareaType::class, ['label' => 'auto_responder.message', 'attr' => ['rows' => 8, 'maxlength' => 100000], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100000)]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['timezone' => 'UTC']);
        $resolver->setAllowedTypes('timezone', 'string');
    }
}
