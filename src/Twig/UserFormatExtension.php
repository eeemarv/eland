<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\UserSimpleCache;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class UserFormatExtension extends AbstractExtension
{
	private $userSimpleCache;
	private $local;
	private $schema;
	private $access;
	private $format = [];

	public function __construct(
		UserSimpleCache $userSimpleCache,
		UrlGeneratorInterface $urlGenerator
	)
	{
		$this->userSimpleCache = $userSimpleCache;
		$this->urlGenerator = $urlGenerator;	
	}

    public function getFilters()
    {
        return [
			new TwigFilter('user_format', [$this, 'get'], [
				'needs_context'		=> true,
			]),        
        ];
    }

	public function get(array $context, int $id, string $schema):string
	{
		if (!isset($this->schema))
		{
			$attributes = $context['app']->getRequest()->attributes;
			$this->schema = $attributes->get('schema');
			$this->access = $attributes->get('access');
		}

		$access = $schema === $this->schema ? $this->access : 'g';

		if (!isset($this->local[$schema]))
		{
			$this->local[$schema] = $this->userSimpleCache->get($schema);
		}

		if (!isset($this->local[$schema][$id]))
		{
			return '';
		}

		$out = '<a href="';
		$out .= $this->urlGenerator->generate('user_show', [
			'id'		=> $id,
			'access'	=> $access,
			'schema'	=> $schema,
			'user_type'	=> $this->local[$schema][$id][0],
		]);
		$out .= '">';
		$out .= $this->local[$schema][$id][1];
		$out .= '</a>';

		return $out;
	}
}
