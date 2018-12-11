<?php

use Symfony\Component\Templating\Helper\Helper;

use service\date_format;

class date_format_helper extends Helper
{
    protected $date_format;

    public function __construct(
        date_format $date_format
    )
    {
        $this->date_format = $date_format;
    }

    public function datepicker_placeholder():string
    {
        return $this->date_format->datepicker_placeholder();
    }

    public function datepicker_format():string
    {
        return $this->date_format->datepicker_format();
    }

    public function get(string $ts, string $precision):string
    {
        return $this->date_format->get($ts, $precision);
    }

    public function get_from_unix(int $unix, string $precision):string
    {
        return $this->date_format->get_from_unix($unix, $precision);
    }

    public function get_day(string $ts):string
    {
        return $this->date_format->get($ts, 'day');
    }

    public function get_min(string $ts):string
    {
        return $this->date_format->get($ts, 'day');
    }

    public function get_sec(string $ts):string
    {
        return $this->date_format->get($ts, 'day');
    }

    public function getName():string
    {
        return 'date_format';
    }
}
