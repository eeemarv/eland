<?php declare(strict_types=1);

namespace service;

use Symfony\Component\HttpFoundation\Request;
use Predis\Client as Predis;
use service\form_token;
use Gregwar\Captcha\CaptchaBuilder;

class captcha
{
	protected $request;
	protected $predis;
	protected $form_token;
	protected $build;

	const TTL = 14400; // 4 hours

	public function __construct(
		Request $request,
		Predis $predis,
		form_token $form_token
	)
	{
		$this->request = $request;
		$this->predis = $predis;
		$this->form_token = $form_token;

		$this->build = new CaptchaBuilder();
		$this->build->setDistortion(false);
		$this->build->setIgnoreAllEffects(true);
		$this->build->build();

		$key = $this->get_key($this->form_token->get(), $this->build->getPhrase());
		$this->predis->set($key, '1');
		$this->predis->expire($key, self::TTL);
	}

	public function get_posted()
	{
		return $this->request->request->get('captcha');
	}

	public function get_form_field():string
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
		$out .= 'value="" required>';
		$out .= '</div>';
		$out .= '<p>';
		$out .= 'Type de code in die hieronder getoond wordt.';
		$out .= '</p>';
		$out .= '<img src="';
		$out .= $this->build->inline();
		$out .= '" alt="Code niet geladen.">';
		$out .= '</div>';

		return $out;
	}

	public function validate():bool
	{
		$key = $this->get_key($this->form_token->get_posted(), $this->get_posted());
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
