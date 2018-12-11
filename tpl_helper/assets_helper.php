<?php

use Symfony\Component\Templating\Helper\Helper;

use service\assets;

class assets_helper extends Helper
{
    protected $assets;

    public function __construct(
        assets $assets
    )
    {
        $this->assets = $assets;
    }

    public function add(array $add):void
    {
        $this->assets->add($add);
    }

    public function get_js():string
    {
        return $this->assets->get_js();
    }

    public function get_css():string
    {
        return $this->assets->get_css();
    }

    public function get_css_print():string
    {
        return $this->assets->get_css_print();
    }

    public function get_version_param():string
    {
        return $this->assets->get_version_param();
    }

    public function getName():string
    {
        return 'assets';
    }
}
