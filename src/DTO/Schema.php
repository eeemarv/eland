<?php declare(strict_types=1);

namespace App\DTO;

/**
 * Schema class represents a schema in the postgres database,
 * which is assiocated with a system/group.
 */
class Schema
{
  public function __construct(
    public readonly string $val
  )
  {
    if (empty($val))
    {
      throw new \InvalidArgumentException('Schema can not be empty');
    }
  }

  public function get(): string
  {
    return $this->val;
  }
}