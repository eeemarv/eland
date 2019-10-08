<?php declare(strict_types=1);

namespace util;

use Silex\Application;
use Symfony\Component\Finder\Finder;

abstract class job_container
{
	protected $app;
	protected $job_type;
	protected $jobs = [];

	public function __construct(Application $app, string $job_type)
	{
		$this->job_type = $job_type;
		$this->app = $app;

		error_log(':: job_type : ' . $this->job_type . ' :: ');

		$finder = new Finder();
		$finder->files()
			->in(__DIR__ . '/../' . $this->job_type)
			->name('*.php');

		foreach ($finder as $file)
		{
			$path = $file->getRelativePathname();

			$job = basename($path, '.php');

			$this->jobs[$job] = $app[$this->job_type . '.' . $job];

			error_log('- ' . $job . ' : ' . $this->jobs[$job]->get_interval());
		}

		error_log(' - - - - - - - - - - - - - - - - -');
	}

	public function should_run()
	{
		return false;
	}

	public function run()
	{
		return $this;
	}
}
