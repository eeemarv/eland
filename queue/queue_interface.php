<?php

namespace queue;

interface queue_interface
{
	public function process(array $data);
	public function queue(array $data, int $priority);
}
