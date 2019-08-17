<?php declare(strict_types=1);

namespace service;

use service\typeahead;
use service\intersystems;

class thumbprint_accounts
{
	protected $typeahead;
	protected $intersystems;

	public function __construct(
		typeahead $typeahead,
		intersystems $intersystems
	)
	{
		$this->typeahead = $typeahead;
		$this->intersystems = $intersystems;
	}

    public function delete(string $status, array $pp_ary, string $tschema):void
    {
        $this->typeahead->delete_thumbprint('accounts', $pp_ary, [
            'status'	=> $status,
        ]);

        if ($status !== 'active')
        {
            return;
        }

        foreach ($this->intersystems->get_eland($tschema) as $remote_schema => $h)
        {
            $this->typeahead->delete_thumbprint('eland_intersystem_accounts',
                $pp_ary, [
                'remote_schema'	=> $remote_schema,
            ]);
        }
    }
}
