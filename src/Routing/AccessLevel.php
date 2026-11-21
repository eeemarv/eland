<?php declare(strict_types=1);

namespace App\Routing;

enum AccessLevel: string
{
  case Admin = 'admin';
  case User = 'user';
  case Guest = 'guest';
  case Anonymous = 'anonymous';

  /**
   * Returns the allowed {role_short} codes based on hierarchy.
   * Returns an empty array for 'anonymous' since /{role_short}/ is omitted.
   *
   * @return string[]
   */
  public function get_allowed_role_short_ary(): array
  {
    return match ($this) {
      self::Admin => ['a'],
      self::User => ['a', 'u'],
      self::Guest => ['a', 'u', 'g'],
      self::Anonymous => [],
    };
  }
}
