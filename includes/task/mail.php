<?php

namespace eland\task;

use League\HTMLToMarkdown\HtmlConverter;
use eland\queue;
use Monolog\Logger;

class mail
{

	protected $converter;
	protected $mailer;
	protected $queue;
	protected $monolog;

	public function __construct(queue $queue, Logger $monolog)
	{
		$this->queue = $queue;
		$this->monolog = $monolog;

		$enc = getenv('SMTP_ENC') ?: 'tls';
		$transport = Swift_SmtpTransport::newInstance(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc)
			->setUsername(getenv('SMTP_USERNAME'))
			->setPassword(getenv('SMTP_PASSWORD'));

		$this->mailer = Swift_Mailer::newInstance($transport);

		$this->mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));

		$this->converter = new HtmlConverter();
		$this->converter->getConfig()->setOption('strip_tags', true);
	}

	/**
	 *
	 */
	public function run(array $data, bool $check_schema = false)
	{

	}

	public function queue(array $data)
	{

	} 
}
