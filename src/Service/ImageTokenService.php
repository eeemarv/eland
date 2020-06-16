<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ImageTokenService
{
    const PREFIX_IMG_UPLOAD = 'image_upload_%route%_%id%_%role_short%_%user_id%_%token%_%schema%';
    const IMG_UPLOAD_TTL = 86400; // 1 day

	protected TokenGeneratorService $token_generator_service;
	protected Predis $predis;
	protected PageParamsService $pp;
	protected SessionUserService $su;

	public function __construct(
		TokenGeneratorService $token_generator_service,
		Predis $predis,
		PageParamsService $pp,
		SessionUserService $su
	)
	{
		$this->token_generator_service = $token_generator_service;
		$this->predis = $predis;
		$this->pp = $pp;
		$this->su = $su;
	}

	public function gen(int $id, string $route):string
	{
        $image_upload_token = $this->token_generator_service->gen();

        $image_upload_token_key = strtr(self::PREFIX_IMG_UPLOAD, [
            '%route%'    	=> $route,
            '%id%'      	=> $id,
            '%token%'   	=> $image_upload_token,
			'%schema%'  	=> $this->pp->schema(),
			'%user_id%'		=> $this->su->id(),
			'%role_short'	=> $this->pp->role_short(),
		]);

        $this->predis->set($image_upload_token_key, '1');
        $this->predis->expire($image_upload_token_key, self::IMG_UPLOAD_TTL);

		return $image_upload_token;
	}

	public function check_and_throw(int $id, string $image_upload_token):void
	{
        $image_upload_token_key = strtr(self::PREFIX_IMG_UPLOAD, [
            '%route%'    	=> $this->pp->route(),
            '%id%'      	=> $id,
            '%token%'   	=> $image_upload_token,
			'%schema%'  	=> $this->pp->schema(),
			'%user_id%'		=> $this->su->id(),
			'%role_short'	=> $this->pp->role_short(),
		]);

		if (!$this->predis->get($image_upload_token_key))
		{
			throw new BadRequestHttpException('No matching image_uplaod_oken.');
		}
	}
}
