<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImageTokenService
{
    const KEY_TPL = 'image_upload_%route%_%id%_%role_short%_%user_id%_%token%_%schema%';
    const TTL = 86400; // 1 day

	protected TokenGeneratorService $token_generator_service;
	protected TranslatorInterface $translator;
	protected Predis $predis;
	protected PageParamsService $pp;
	protected SessionUserService $su;

	public function __construct(
		TokenGeneratorService $token_generator_service,
		TranslatorInterface $translator,
		Predis $predis,
		PageParamsService $pp,
		SessionUserService $su
	)
	{
		$this->token_generator_service = $token_generator_service;
		$this->translator = $translator;
		$this->predis = $predis;
		$this->pp = $pp;
		$this->su = $su;
	}

	public function gen(int $id, string $route):string
	{
        $image_token = $this->token_generator_service->gen();

        $image_token_key = strtr(self::KEY_TPL, [
            '%route%'    	=> $route,
            '%id%'      	=> $id,
            '%token%'   	=> $image_token,
			'%schema%'  	=> $this->pp->schema(),
			'%user_id%'		=> $this->su->id(),
			'%role_short'	=> $this->pp->role_short(),
		]);

        $this->predis->set($image_token_key, '1');
        $this->predis->expire($image_token_key, self::TTL);

		return $image_token;
	}

	public function get_error_response(int $id, string $image_token):?Response
	{
        $image_token_key = strtr(self::KEY_TPL, [
            '%route%'    	=> $this->pp->route(),
            '%id%'      	=> $id,
            '%token%'   	=> $image_token,
			'%schema%'  	=> $this->pp->schema(),
			'%user_id%'		=> $this->su->id(),
			'%role_short'	=> $this->pp->role_short(),
		]);

		if ($this->predis->get($image_token_key))
		{
			return null;
		}

		return new JsonResponse([
			'error'	=> $this->translator->trans('image_upload.error.token'),
			'code'	=> 400,
		], 400);
	}
}
