<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Repository\UserRepository;
use App\Service\PageParamsService;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class TypeaheadUserTransformer implements DataTransformerInterface
{
  public function __construct(
    private readonly UserRepository $user_repository,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function transform($id): mixed
  {
    if (null === $id)
    {
      return '';
    }

    $account_str = $this->user_repository->get_account_str(
      id: $id,
      schema: $this->pp->schema_o(),
    );

    return $account_str;
  }

  public function reverseTransform($account_str): mixed
  {
    if (!$account_str)
    {
      return null;
    }

    [$code] = explode(' ', $account_str);

    $id = $this->user_repository->get_by_typeahead_code(
      code: $code,
      schema: $this->pp->schema_o(),
    );

    if (!$id)
    {
      throw new TransformationFailedException('user account with code ' . $code . ' does not exist.');
    }

    return $id;
  }
}
