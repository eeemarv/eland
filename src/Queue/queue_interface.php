<?php declare(strict_types=1);

namespace App\Queue;

interface queue_interface
{
	public function process(array $data);
	public function queue(array $data, int $priority);
}
