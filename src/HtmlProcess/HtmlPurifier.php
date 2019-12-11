<?php declare(strict_types=1);

namespace App\HtmlProcess;

class HtmlPurifier
{
	protected $purifier;

	public function __construct()
	{
		$config_purifier = \HTMLPurifier_Config::createDefault();
		$config_purifier->set('Cache.DefinitionImpl', null);
		$this->purifier = new \HTMLPurifier($config_purifier);
	}

	public function purify(string $content):string
	{
		$content = str_replace(['<p></p>', '<p>&nbsp;</p>', '<p><br></p>'], '', $content);
		$content = trim($content);

		return $this->purifier->purify($content);
	}
}
