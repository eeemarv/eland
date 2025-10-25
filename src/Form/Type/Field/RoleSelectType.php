<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleSelectType extends AbstractType
{
  public function __construct(
    private readonly ConfigService $config_service,
    private readonly PageParamsService $pp,
  )
  {
  }

  private function get_choices():array
  {
    $choices = [
      'access.admin.label'  => 'admin',
      'access.user.label'   => 'user',
    ];

    if ($this->config_service->get_intersystem_en($this->pp->schema()))
    {
      $choices['access.guest.label'] = 'guest';
    }

    return $choices;
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('choices', $this->get_choices());
  }

  public function getParent():string
  {
    return ChoiceType::class;
  }
}