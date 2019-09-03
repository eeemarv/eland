<?php declare(strict_types=1);

namespace twig;

use service\alert as service_alert;

class alert
{
	protected $service_alert;

	public function __construct(service_alert $service_alert)
	{
		$this->service_alert = $service_alert;
	}

	public function get_ary():array
	{
		return $this->service_alert->get_ary();
	}
}
