<?php declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Mime\Address;

/**
 * AddressAry class represents an array of email addresses.
 * It ensures that all elements in the array are instances of Symfony\Component\Mime\Address.
 */
class AddressAry
{
  public function __construct(
    public readonly array $ary
  )
  {
    foreach ($ary as $address) {
      if (!$address instanceof Address) {
        throw new \InvalidArgumentException('All elements must be instances of Symfony\Component\Mime\Address');
      }
    }
  }

  public function ary(): array
  {
    return $this->ary;
  }

  public function count(): int
  {
    return count($this->ary);
  }

  /**
   * for logging
   */
  public function str():string
  {
    return implode(', ', array_map(fn(Address $address) => $address->toString(), $this->ary));
  }

  /**
   * To store in db
   */
  public function adr_str_ary():array
  {
    return array_map(fn(Address $address) => $address->getAddress(), $this->ary);
  }
}