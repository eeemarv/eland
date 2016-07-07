<?php

class date_format
{
	private $month_abbrev_ary = ['Jan', 'Feb', 'Maa', 'Apr', 'Mei', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];

	
	/**
	 *
	 */

	public function __construct()
	{
	}


	/*
	 *
	 */

	function format_date($ts, $out_format = '')
	{

		$time = strtotime($ts . ' UTC');
		$time = date('Y-m-d H:i', $time);

		return $time;
	}
}
