<?php

use Symfony\Component\Templating\Helper\Helper;

use service\config;
use service\this_group;

class config_helper extends Helper
{
    protected $config;
    protected $this_group;
    protected $schema;

    public function __construct(
        config $config,
        this_group $this_group
    )
    {
        $this->config = $config;
        $this->this_group = $this_group;
        $this->schema = $this_group->get_schema();
    }

    public function get_remote(string $name, string $schema):string
    {
        return $this->config->get($name, $schema);
    }

    public function get(string $name):string
    {
        return $this->config->get($name, $this->schema);
    }

    public function getName():string
    {
        return 'config';
    }
}
