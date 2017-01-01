<?php

namespace eland;

interface queue_interface
{
	public function process(array $data);

	public function queue(array $data);

	public function get_interval();
}
