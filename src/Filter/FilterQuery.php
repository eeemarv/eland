<?php declare(strict_types=1);

namespace App\Filter;

use App\Filter\AbstractFilter;

class FilterQuery
{
    private $where = [];
    private $params = [];
    private $whereQueryString;

    public function add(AbstractFilter $filter)
    {
        $orWhere =$filter->getOrWhere();
        $andWhere = $filter->getAndWhere(); 

        $countOrWhere = count($orWhere);

        if ($countOrWhere !== 0)
        {
            $multiItem = $countOrWhere > 1;
            $orWhereItem = $multiItem ? '(' : '';
            $orWhereItem .= implode(' or ', $orWhere);
            $orWhereItem .= $multiItem ? ')' : '';
            $this->where[] = $orWhereItem;
        }

        $this->where = array_merge($this->where, $filter->getAndWhere());
        $this->params = array_merge($this->params, $filter->getParams());

        return $this;
    }

    public function isFiltered():bool
    {
        return count($this->where) !== 0;
    }

    public function getParams():array
    {
        return $this->params;
    }

    public function getWhereAry():array
    {
        return $this->where;
    }

    public function getWhereQueryString():string 
    {
        if (isset($this->whereQueryString))
        {
            return $this->whereQueryString;
        }

        $this->whereQueryString = $this->isFiltered() ? ' where ' . implode(' and ', $this->where) : '';

        return $this->whereQueryString;
    }
}