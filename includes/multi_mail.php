<?php

namespace eland;

class multi_mail
{
	private $text = '';
	private $html = '';
	private $out = [];
	private $vars = [];
	private $cond = [];

	public function __construct()
	{
	}

	public function add($key, $value, $cond = true)
	{
		$out = [$key => $value];

		if (is_string($cond))
		{
			if (strpos($cond, ':') !== false)
			{
				list($key, $when) = explode(':', $cond);

				if ($when == 'en')
				{
					$this->cond[$key] = true;
				}
				else if ($this->cond[$key] && $when == 'none')
				{
					return $this;
				}

				$out['cond_when'] = $when;
				$out['cond_key'] = $key;
			}
			else
			{
				$out['cond'] = $cond;
			}
		}
		else if (!$cond)
		{
			return $this;
		}
		$this->out[] = $out;

		return $this;
	}

	public function add_text_and_html($in, $cond = true)
	{
		$this->add_text($in, $cond);
		return $this->add_html($in, $cond);
	}

	public function add_text($text, $cond = true)
	{
		return $this->add('text', $text, $cond);
	}

	public function add_html($html, $cond = true)
	{
		return $this->add('html', $html, $cond);
	}

	public function add_text_var($key, $cond = true)
	{
		return $this->add('text_var', $key, $cond);
	}

	public function add_html_var($key, $cond = true)
	{
		return $this->add('html_var', $key, $cond);
	}

	public function set_var($key, $value)
	{
		$this->vars[$key] = $value;
		return $this;
	}

	public function set_vars($ary)
	{
		$this->vars = array_merge($this->vars, $ary);
		return $this;
	}

	public function mail_q($mail_ary = [])
	{
		$html = $text = '';

		foreach ($this->out as $out)
		{
			if (isset($out['cond_when']))
			{
				if ($out['cond_when'] == 'en')
				{
					$cond = true;
				}
				else if ($out['cond_when'] == 'none' && !$this->cond[$out['cond_key']])
				{
					$cond = true;
				}
				else if ($out['cond_when'] == 'any' && $this->cond[$out['cond_key']])
				{
					$cond = true;
				}
				else
				{
					$cond = false;
				}
			}
			else
			{
				$cond = (!isset($out['cond']) || $this->vars[$out['cond']]) ? true : false;
			}

			$text .= (isset($out['text']) &&  $cond) ? $out['text'] : '';
			$text .= (isset($out['text_var']) && $cond) ? $this->vars[$out['text_var']] : '';
			$html .= (isset($out['html']) && $cond) ? $out['html'] : '';
			$html .= (isset($out['html_var']) && $cond) ? $this->vars[$out['html_var']] : '';
		}

		$out = ['text' => $text];

		if ($html)
		{
			$out['html'] = $html;
		}

		mail_q(array_merge($mail_ary, $out));
		$this->vars = [];

		return $this;
	}
}
