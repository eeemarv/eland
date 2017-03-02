<?php

namespace eland\util;

class geo
{
	public $lat;
	public $lng;

	public function __construct(float $lat, float $lng)
	{
		$this->lat = $lat;
		$this->lng = $lng;
	}

	public function getLat() // test templ
	{
		return $this->lat;
	}

	public function getLng() // test templ
	{
		return $this->lng;
	}

	public function get_lat()
	{
		return $this->lat;
	}

	public function get_lng()
	{
		return $this->lng;
	}

	public function set_lat(float $lat)
	{
		$this->lat = $lat;
		return $this;
	}

	public function set_lng(float $lng)
	{
		$this->lat = $lng;
		return $this;
	}
}
