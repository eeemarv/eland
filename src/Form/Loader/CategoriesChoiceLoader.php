<?php declare(strict_types=1);

namespace App\Form\Loader;

use App\Repository\CategoryRepository;
use App\Service\PageParamsService;
use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CategoriesChoiceLoader extends AbstractChoiceLoader implements ChoiceLoaderInterface
{
  protected $choice_list;

  public function __construct(
      private readonly bool $parent_selectable,
      private readonly bool $null_selectable,
      private readonly bool $all_choice,
      private readonly CategoryRepository $category_repository,
      private readonly PageParamsService $pp,
      private readonly TranslatorInterface $translator,
  )
  {
  }

  public function loadChoices():iterable
  {
    $categories = $this->category_repository->get_all(
      schema: $this->pp->schema_o(),
    );
    $choices = [];

    if ($this->all_choice){
      $choices[$this->translator->trans('categories_select_type.all_choice')] = '';
    }

    if ($this->null_selectable){
      $choices[$this->translator->trans('categories_select_type.null_choice')] = 'null';
    }

    if ($this->parent_selectable)
    {
      foreach ($categories as $cat)
      {
        $prefix = isset($cat['parent_id']) ?  '. > . ' : '';
        $choices[$prefix . $cat['name']] = $cat['id'];
      }
    }
    else
    {
      $parent_name = '***';

      foreach ($categories as $cat)
      {
        if (isset($cat['parent_id']))
        {
          if (!is_array($choices[$parent_name]))
          {
            $choices[$parent_name] = [];
          }

          $choices[$parent_name][$cat['name']] = $cat['id'];
          continue;
        }

        $parent_name = $cat['name'];

        if (isset($choices[$parent_name]))
        {
          error_log('Parent category already exists: ' . $cat['name'] . ', cat_id: ' . $cat['id']);
        }

        $choices[$parent_name] = $cat['id'];
      }
    }

    return $choices;
  }
}