<?php declare(strict_types=1);

namespace App\Filter;

use Symfony\Component\HttpFoundation\Request;
use App\Form\Filter\TransactionFilterType;

use App\Filter\AbstractFormFilter;

class TransactionFilter extends AbstractFormFilter
{
    public function filter()
    {
        $this->andWhere = $this->orWhere = $this->params = [];

		$this->filter = $this->formFactory->createNamedBuilder('f', TransactionFilterType::class, ['andor' => 'and'])
			->getForm()
			->handleRequest($this->request);

		if ($this->filter->isSubmitted() && $this->filter->isValid())
		{
			$data = $this->filter->getData();

			if (isset($data['q']))
			{
				$this->andWhere[] = 't.description ilike ?';
				$this->params[] = '%' . $data['q'] . '%';
			}

			$whereCode = [];

			if (isset($data['from_user']))
			{
				$whereCode[] = $data['andor'] === 'nor' ? 't.id_from <> ?' : 't.id_from = ?';
				$this->params[] = $data['from_user'];
			}

			if (isset($data['to_user']))
			{
				$whereCode[] = $data['andor'] === 'nor' ? 't.id_to <> ?' : 't.id_to = ?';
				$this->params[] = $data['to_user'];
			}

			if (count($whereCode) > 1 && $data['andor'] === 'or')
			{
				$whereCode = ['(' . implode(' or ', $whereCode) . ')'];
			}

			$this->andWhere = array_merge($this->andWhere, $whereCode);

			if (isset($data['from_date']))
			{
				$this->andWhere[] = 't.date >= ?';
				$this->params[] = $data['from_date'];
			}

			if (isset($data['to_date']))
			{
				$this->andWhere[] = 't.date <= ?';
				$this->params[] = $data['to_date'];
			}
        }

		return $this;
    }
}