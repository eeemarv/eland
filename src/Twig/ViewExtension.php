<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\SessionView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ViewExtension extends AbstractExtension
{
	private $view;

	public function __construct(SessionView $sessionView)
	{
		$this->sessionView = $sessionView;
	}

	public function getFilters()
    {
		return [
			new TwigFilter('view', [$this, 'get']),           
        ];
    }

	public function get(array $params, string $entity):array
	{
		return $this->sessionView->merge($params, $entity);
	}
}
