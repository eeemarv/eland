<?php

namespace eland;

use eland\this_group;
use eland\user;


class base_twig_extension extends Twig_Extension
{
	protected $this_group;
	protected $user;

	public function __construct(this_group $this_group, user $user)
	{
		$this->this_group = $this_group;
		$this->user = $user;
	}

    public function getGlobals()
    {
        return [
            'someStuff' => $this->myVar,
            // ...
        ];
    }
}
