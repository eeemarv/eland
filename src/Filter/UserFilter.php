<?php declare(strict_types=1);

namespace App\Filter;

use Symfony\Component\HttpFoundation\Request;
use App\Form\Filter\UserFilterType;

use App\Filter\AbstractFormFilter;

class UserFilter extends AbstractFormFilter
{
    public function filter()
    {
        $this->andWhere = $this->orWhere = $this->params = [];

		$this->filter = $this->formFactory->createNamedBuilder('f', UserFilterType::class)
			->getForm()
			->handleRequest($this->request);

		if ($this->filter->isSubmitted() && $this->filter->isValid())
		{
			$data = $this->filter->getData();

			if (isset($data['q']))
			{
				$this->orWhere[] = 'u.name ilike ?';
				$this->params[] = '%' . $data['q'] . '%';
		
				$this->orWhere[] = 'u.letscode ilike ?';
				$this->params[] = '%' . $data['q'] . '%';	
				
				$this->orWhere[] = 'u.postcode ilike ?';
				$this->params[] = '%' . $data['q'] . '%';					
			}
        }

		return $this;
    }
}
