<?php

namespace eland\model;

interface queue
{
	public function process(array $data);

	public function queue(array $data);

	public function get_interval();
}
