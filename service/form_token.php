<?php declare(strict_types=1);

namespace service;

use Symfony\Component\HttpFoundation\Request;
use Predis\Client as Predis;
use service\token as token_gen;

class form_token
{
	protected $request;
	protected $predis;
	protected $token_gen;
	protected $token;

	const TTL = 14400; // 4 hours
	const NAME = 'form_token';
	const STORE_PREFIX = 'form_token_';

	public function __construct(
		Request $request,
		Predis $predis,
		token_gen $token_gen
	)
	{
		$this->request = $request;
		$this->predis = $predis;
		$this->token_gen = $token_gen;
	}

	public function get_posted():string
	{
		return $this->request->request->get(self::NAME, '');
	}

	public function get():string
	{
		if (!isset($this->token))
		{
			$this->token = $this->token_gen->gen();
			$key = self::STORE_PREFIX . $this->token;
			$this->predis->set($key, '1');
			$this->predis->expire($key, self::TTL);
		}

		return $this->token;
	}

	public function get_hidden_input():string
	{
		return '<input type="hidden" name="form_token" value="' . $this->get() . '">';
	}

	public function get_error():string
	{
		if ($this->get_posted() === '')
		{
			return 'Het formulier bevat geen form token';
		}

		$key = self::STORE_PREFIX . $this->get_posted();

		$value = $this->predis->get($key);

		if (!$value)
		{
			return 'Het formulier is verlopen';
		}

		if ($value > 1)
		{
			$this->predis->incr($key);
			return 'Een dubbele ingave van het formulier werd voorkomen.';
		}

		$this->predis->incr($key);

		return '';
	}

	public function get_param_ary():array
	{
		return [self::NAME => $this->get()];
	}

	public function get_ajax_error(string $form_token):string
	{
		if ($form_token === '')
		{
			return 'Geen form token gedefiniëerd.';
		}
		else if (!$this->predis->get(self::STORE_PREFIX . $form_token))
		{
			return 'Formulier verlopen of ongeldig.';
		}

		return '';
	}

	public function get_query():string
	{
		return $this->request->query->get(self::NAME, '');
	}
}
