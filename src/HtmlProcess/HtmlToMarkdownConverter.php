<?php declare(strict_types=1);

namespace App\HtmlProcess;

use League\HTMLToMarkdown\HtmlConverter;

class HtmlToMarkdownConverter
{
	protected $converter;

	public function __construct()
	{
		$this->converter = new HtmlConverter();
		$converter_config = $this->converter->getConfig();
		$converter_config->setOption('strip_tags', true);
		$converter_config->setOption('remove_nodes', 'img');
	}

	public function convert(string $content):string
	{
		return $this->converter->convert($content);
	}
}
