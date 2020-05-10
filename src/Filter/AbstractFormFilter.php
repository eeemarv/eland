<?php declare(strict_types=1);

namespace App\Filter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use App\Filter\AbstractFilter;

abstract class AbstractFormFilter extends AbstractFilter
{
    protected $formFactory;
    protected $request;
    protected $filter;

    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;       
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    abstract public function filter();

    public function createView():FormView
    {
        return $this->filter->createView();
    }
}