<?php

class date_format
{
	private $formats = [
		'Y-m-d H:i:s' => [
			'date'	=> 'Y-m-d',
			'min'	=> 'Y-m-d H:i',
		],
		'd-m-Y H:i:s' => [
			'date'	=> 'd-m-Y',
			'min'	=> 'd-m-Y H:i',
		],
		'j M Y, H:i:s' => [
			'date'	=> 'j M Y',
			'min'	=> 'j M Y H:i',
		],
		'j F Y H:i:s'	=> [
			'date'	=> 'j F Y',
			'min'	=> 'j F Y H:i',
		],
	];

	private $format;

	private $format_ary = [];

	/**
	 *
	 */

	public function __construct()
	{
		$this->format = readconfigfromdb('date_format');

		if (!$this->format)
		{
			$this->format = '§j M Y,§ H:i';
		}

		$pos_m = strpos($this->format, 'M');


		$pos_f = strpos($this->format, 'F');

		$this->format_ary = $this->formats[$this->format];
	}

	/*
	 *
	 */

	function format_parse()
	{
	}

	/*
	 *
	 */

	function get($ts = false, $precision = 'min')
	{
		$time = ($ts) ? strtotime($ts . ' UTC') : time();

		$time = date('Y-m-d H:i', $time);

		$time = strftime('%a %e %b %G');

		return $time;
	}

	function convert_date_to_datepicker($date)
	{

	}

	function convert_datepicker_to_date($picker)
	{

	}

	function get_datepicker_format()
	{
		$search = ['j', 'd', 'n', 'm', 'Y', 'F'];
		$replace = ['d', 'dd', 'm', 'mm', 'yyyy', 'MM'];

		return str_replace($search, $replace, $this->format_ary['date']);
	}

	function get_datepicker_placeholder()
	{
		$search = ['j', 'd', 'n', 'm', 'Y', 'M', 'F'];
		$replace = ['d', 'dd', 'm', 'mm', 'jjjj', 'mnd', 'maand'];

		return str_replace($search, $replace, $this->format_ary['date']);
	}

	function get_format_options()
	{
		$options = [];

		foreach ($this->formats as $format => $prec)
		{
			$options[$format] = date($format); // replace 
		}

		return $options;
	}
	
}
