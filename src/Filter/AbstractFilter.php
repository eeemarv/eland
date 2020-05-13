<?php declare(strict_types=1);

namespace App\Filter;

abstract class AbstractFilter
{
    protected $orWhere;
    protected $andWhere;
    protected $whereString;
    protected $params;

    abstract public function filter();

    public function isFiltered():bool
    {
        return (count($this->orWhere) + count($this->andWhere)) !== 0;
    }

    public function getOrWhere():array
    {
        return $this->orWhere;
    }

    public function getAndWhere():array
    {
        return $this->andWhere;
    }

    public function getWhereQueryString():string 
    {
        if (isset($this->whereString))
        {
            return $this->whereString;
        }

        $hasAndWhere = count($this->andWhere) !== 0;
        $this->whereString = '';

        if (count($this->orWhere) !== 0)
        {
            $this->whereString .= $hasAndWhere ? '(' : '';
            $this->whereString .= implode(' or ', $this->orWhere);
            $this->whereString .= $hasAndWhere ? ') and ' : '';
        }

        $this->whereString .= $hasAndWhere ? implode(' and ', $this->andWhere) : '';

        return $this->whereString;
    }

    public function getParams():array
    {
        return $this->params;
    }
}