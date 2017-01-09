<?php

namespace eland\model;

interface queue_interface
{
	public function process(array $data);

	public function queue(array $data);

	public function get_interval();

	public function get_next();

	public function set_next(int $next);
}
