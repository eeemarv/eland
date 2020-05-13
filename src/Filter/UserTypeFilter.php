<?php declare(strict_types=1);

namespace App\Filter;

use App\Filter\AbstractFilter;

class UserTypeFilter extends AbstractFilter
{
    private $type;
    private $newUserTreshold;

    public function setType(string $type)
    {
        $this->type = $type;
        return $this;
    }

    public function setNewUserTreshold(string $newUserTreshold)
    {
        $this->newUserTreshold = $newUserTreshold;
        return $this;
    }

    public function filter()
    {
        $this->orWhere = $this->andWhere = $this->params = [];

		switch ($this->type)
		{
			case 'active': 
				$this->andWhere[] = 'u.status in (1, 2, 7)';
				break;
			case 'new':
				$this->andWhere[] = 'u.status = 1';
				$this->andWhere[] = 'u.adate is not null';
				$this->andWhere[] = 'u.adate > ?';
				$this->params[] = $this->newUserTreshold;
				break;
			case 'leaving':
				$this->andWhere[] = 'u.status = 2';
				break;			
			case 'interlets': 
				$this->andWhere[] = 'u.accountrole = \'interlets\'';
				$this->andWhere[] = 'u.status in (1, 2, 7)';
				break;
			case 'direct':
				$this->andWhere[] = 'u.status in (1, 2, 7)';
				$this->andWhere[] = 'u.accountrole != \'interlets\'';
				break;
			case 'pre-active':
				$this->andWhere[] = 'u.adate is null';
				$this->andWhere[] = 'u.status not in (1, 2, 7)';
				break;
			case 'post-active':
				$this->andWhere[] = 'u.adate is not null';
				$this->andWhere[] = 'u.status not in (1, 2, 7)';
				break;
			case 'all':
				break;
			default: 
				$this->andWhere[] = '1 = 2';
				break;
        }   
	}
}
