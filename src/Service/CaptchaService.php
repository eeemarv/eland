<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;
use App\Service\FormTokenService;
use Gregwar\Captcha\CaptchaBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class CaptchaService
{
	protected $request;
	protected $predis;
	protected $form_token_service;
	protected $build;

	const TTL = 14400; // 4 hours

	public function __construct(
		RequestStack $request_stack,
		Predis $predis,
		FormTokenService $form_token_service
	)
	{
		$this->request = $request_stack->getCurrentRequest();
		$this->predis = $predis;
		$this->form_token_service = $form_token_service;

		$this->build = new CaptchaBuilder();
		$this->build->setDistortion(false);
		$this->build->setIgnoreAllEffects(true);
		$this->build->build();

		$key = $this->get_key($this->form_token_service->get(), $this->build->getPhrase());
		$this->predis->set($key, '1');
		$this->predis->expire($key, self::TTL);
	}

	public function get_posted()
	{
		return $this->request->request->get('captcha');
	}

	public function get_form_field(bool $disabled = false):string
	{
		$out = '<div class="form-group">';
		$out .= '<label for="captcha">';
		$out .= 'Anti-spam verificatiecode';
		$out .= '</label>';
		$out .= '<div class="input-group">';
		$out .= '<span class="input-group-addon">';
		$out .= '<i class="fa fa-code"></i>';
		$out .= '</span>';
		$out .= '<input type="text" class="form-control" id="captcha" name="captcha" ';
		$out .= 'value="" required';
		$out .= $disabled ? ' disabled' : '';
		$out .= '>';
		$out .= '</div>';
		$out .= '<p>';
		$out .= 'Typ de code die hieronder getoond wordt.';
		$out .= '</p>';
		$out .= '<img src="';
		$out .= $this->build->inline();
		$out .= '" alt="Code niet geladen.">';
		$out .= '</div>';

		return $out;
	}

	public function validate():bool
	{
		$key = $this->get_key($this->form_token_service->get_posted(), $this->get_posted());
		return $this->predis->get($key) ? true : false;
	}

	public function get_key(string $form_token, string $phrase):string
	{
		return strtr('captcha_%form_token%_%phrase%', [
			'%form_token%'	=> $form_token,
			'%phrase'		=> $phrase,
		]);
	}
}
