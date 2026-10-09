<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlatpickrType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
      $builder->addModelTransformer(new CallbackTransformer(
      // Van DateTimeImmutable naar string (voor de HTML value attribute)
      function (? \DateTimeImmutable $dateTime): ?string {
          return $dateTime ? $dateTime->format('Y-m-d') : null;
      },
      // Van string (HTML/Flatpickr submit) naar DateTimeImmutable
      function (?string $dateString): ?\DateTimeImmutable {
          if (!$dateString) {
              return null;
          }

          $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateString);
          return $date ?: null;
        }
      ));
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setDefaults([
      'attr' => [
        'data-controller' => 'flatpickr',
        'data-flatpickr-date-format-value' => 'Y-m-d',
        'data-flatpickr-alt-format-value' => 'D j M Y',
        'autocomplete' => 'off',
      ],
    ]);
  }

  public function getParent(): string
  {
    return TextType::class;
  }
}
