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

    public function datepicker_placeholder(string $schema):string
    {
        return $this->date_format->datepicker_placeholder($schema);
    }

    public function datepicker_format(string $schema):string
    {
        return $this->date_format->datepicker_format($schema);
    }

    public function get(string $ts, string $precision, string $schema):string
    {
        return $this->date_format->get($ts, $precision, $schema);
    }

    public function get_from_unix(int $unix, string $precision, string $schema):string
    {
        return $this->date_format->get_from_unix($unix, $precision, $schema);
    }

    public function get_day(string $ts, string $schema):string
    {
        return $this->date_format->get($ts, 'day', $schema);
    }

    public function get_min(string $ts, string $schema):string
    {
        return $this->date_format->get($ts, 'min', $schema);
    }

    public function get_sec(string $ts, string $schema):string
    {
        return $this->date_format->get($ts, 'sec', $schema);
    }

    public function getName():string
    {
        return 'date_format';
    }
}
