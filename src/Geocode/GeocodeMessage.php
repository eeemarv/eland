<?php declare(strict_types=1);

namespace App\Geocode;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('geo')]
class GeocodeMessage
{
  public function __construct(
    public readonly int $contact_id,
    public readonly Schema $schema
  )
  {
  }
}
