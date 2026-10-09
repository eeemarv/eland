<?php declare(strict_types=1);

namespace App\Twig;

use App\DTO\Schema;
use App\Service\ConfigService;
use Twig\Attribute\AsTwigFunction;

class ConfigExtension
{
	public function __construct(
		private readonly ConfigService $config_service,
	)
	{
	}

  #[AsTwigFunction(name: 'config_str', needsContext: true)]
	public function get_str(
    array $context,
    string $config_id,
    string|null $schema = null
  ):string
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);
		return $this->config_service->get_str(
      config_id: $config_id,
      schema: $schema_o,
    );
	}

  #[AsTwigFunction(name: 'config_bool', needsContext: true)]
	public function get_bool(
    array $context,
    string $config_id,
    string|null $schema = null,
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);
		return $this->config_service->get_bool(
      config_id: $config_id,
      schema: $schema_o,
    );
	}

  #[AsTwigFunction(name: 'config_int', needsContext: true)]
	public function get_int(
    array $context,
    string $config_id,
    string|null $schema = null,
  ):int|null
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);
		return $this->config_service->get_int(
      config_id: $config_id,
      schema: $schema_o,
    );
	}

  #[AsTwigFunction(name: 'config_ary', needsContext: true)]
	public function get_ary(
    array $context,
    string $config_id,
    string|null $schema = null,
  ):array
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);
		return $this->config_service->get_ary(
      config_id: $config_id,
      schema: $schema_o,
    );
	}
}
