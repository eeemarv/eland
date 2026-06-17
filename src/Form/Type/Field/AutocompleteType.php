<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AutocompleteType extends AbstractType
{
  public function __construct(
    private readonly PageParamsService $pp,
    private readonly UrlGeneratorInterface $url_generator,
  )
  {
  }

  public function buildView(
    FormView $view,
    FormInterface $form,
    array $options
  ):void
  {
    $view->vars['attr']['data-autocomplete-url-value'] = $this->url_generator->generate(
      $options['route'], [
        ...$options['route_params'],
        ...$this->pp->ary(),
      ],
    );
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('route', null);
    $resolver->setAllowedTypes('route', 'string');
    $resolver->setRequired('route');
    $resolver->setDefault('route_params', []);
    $resolver->setAllowedTypes('route_params', 'array');
    $resolver->setDefault('attr', [
      'data-controller' => 'autocomplete',
    ]);
  }

  public function getParent():string
  {
    return TextType::class;
  }

  public function getBlockPrefix():string
  {
    return 'autocomplete';
  }
}