<?php declare(strict_types=1);

namespace App\Queue;

interface QueueInterface
{
	public function process(array $data):void;
	public function queue(array $data, int $priority):void;
}
