<?php declare(strict_types=1);

namespace App\HtmlProcess;

class HtmlPurifier
{
	protected \HTMLPurifier $purifier;

	public function __construct()
	{
		$config = \HTMLPurifier_HTML5Config::createDefault();
		// $config_purifier = \HTMLPurifier_Config::createDefault();
		$config->set('Cache.DefinitionImpl', null);
		$this->purifier = new \HTMLPurifier($config);
	}

	public function purify(string $content):string
	{
		$content = str_replace(['<p></p>', '<p>&nbsp;</p>', '<p><br></p>'], '', $content);
		$content = trim($content);

		return $this->purifier->purify($content);
	}
}
