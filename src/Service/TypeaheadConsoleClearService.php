<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;

class TypeaheadConsoleClearService
{
	protected Predis $predis;
	protected SystemsService $systems_service;

	public function __construct(
		Predis $predis,
		SystemsService $systems_service
	)
	{
		$this->predis = $predis;
		$this->systems_service = $systems_service;
	}

	public function clear_all():void
	{
		$schemas = $this->systems_service->get_schemas();

		foreach($schemas as $schema)
		{
			$this->predis->del(TypeaheadService::STORE_DATA_PREFIX . $schema);
			$this->predis->del(TypeaheadService::STORE_THUMBPRINT_PREFIX . $schema);
		}

		error_log('+-------------------------+');
		error_log('| Typeahead cache cleared |');
		error_log('+-------------------------+');
	}
}
