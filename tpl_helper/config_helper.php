<?php

use Symfony\Component\Templating\Helper\Helper;

use service\config;

class config_helper extends Helper
{
    protected $config;

    public function __construct(
        config $config,
        this_group $this_group
    )
    {
        $this->config = $config;
    }

    public function get(string $name, string $schema):string
    {
        return $this->config->get($name, $schema);
    }

    public function getName():string
    {
        return 'config';
    }
}
