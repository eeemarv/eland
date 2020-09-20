<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\StatusCnst;
use App\Service\TypeaheadService;
use App\Service\IntersystemsService;

class ThumbprintAccountsService
{
	protected TypeaheadService $typeahead_service;
	protected IntersystemsService $intersystems_service;

	public function __construct(
		TypeaheadService $typeahead_service,
		IntersystemsService $intersystems_service
	)
	{
		$this->typeahead_service = $typeahead_service;
		$this->intersystems_service = $intersystems_service;
	}

    public function delete(array $pp_ary, string $pp_schema):void
    {
        foreach (StatusCnst::THUMBPINT_ARY as $status)
        {
            $this->typeahead_service->delete_thumbprint('accounts', $pp_ary, [
                'status'	=> $status]);
        }

        foreach ($this->intersystems_service->get_eland($pp_schema) as $remote_schema => $h)
        {
            $this->typeahead_service->delete_thumbprint('eland_intersystem_accounts',
                $pp_ary, [
                'remote_schema'	=> $remote_schema,
            ]);
        }
    }
}
