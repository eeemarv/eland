<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use Redis;
use App\Service\TokenGeneratorService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CaptchaType extends AbstractType
{
    const KEY_PREFIX = 'captcha.';
    const TTL = 86400;

    public function __construct(
        protected Redis $redis,
        protected TokenGeneratorService $token_generator_service,
        #[Autowire('%kernel.project_dir%')]
        protected $project_dir
    )
    {
    }

    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options
    ):void
    {
        $width = $options['width'];
        $height = $options['height'];
        $char_count = $options['char_count'];
        $char_pool = $options['char_pool'];
        $max_deg = $options['max_deg'];
        $max_x_offset = $options['max_x_offset'];
        $max_y_offset = $options['max_y_offset'];
        $spacing = $options['spacing'];
        $font = $options['font'];
        $char_len = strlen($char_pool) - 1;

        $captcha_code = '';
        for ($i = 0; $i < $char_count; $i++)
        {
            $captcha_code .= $char_pool[random_int(0, $char_len)];
        }
        $captcha_token = $this->token_generator_service->gen();
        $key = self::KEY_PREFIX . $captcha_token;
        $this->redis->set($key, $captcha_code, self::TTL);

        $im = imagecreate($width, $height);
        $rb = mt_rand(210, 255);
        $gb = mt_rand(210, 255);
        $bb = mt_rand(210, 255);
        $bg = imagecolorallocate($im, $rb, $gb, $bb);
        $rf = mt_rand(0, 100);
        $gf = mt_rand(0, 100);
        $bf = mt_rand(0, 100);
        $fg = imagecolorallocate($im, $rf, $gf, $bf);
        imagefill($im, 0, 0, $bg);
        $size = (int) round($width / $char_count) - $spacing;
        $b = imagettfbbox($size, 0, $font, $captcha_code);
        $code_width = $b[2] - $b[0];
        $code_height = $b[1] - $b[7];
        $x_pos = (int) round(($width - $code_width) / 2);
        $y_pos = (int) round(($height - $code_height) / 2) + $size;

        for ($i=0; $i < $char_count; $i++) {
            $bc = imagettfbbox($size, 0, $font, $captcha_code[$i]);
            $deg = mt_rand(-$max_deg, $max_deg);
            $y_offset = mt_rand(-$max_y_offset, $max_y_offset);
            $x_offset = mt_rand(-$max_x_offset, $max_x_offset);
            imagettftext($im, $size, $deg, $x_pos + $x_offset, $y_pos + $y_offset, $fg, $font, $captcha_code[$i]);
            $x_pos += $spacing + $bc[2] - $bc[0];
        }

        ob_start();
        imagepng($im);
        $image = ob_get_clean();
        imagedestroy($im);
        $captcha_image = 'data:image/png;base64,' . base64_encode($image);

        $view->vars['value'] = '';

        parent::buildView($view, $form, $options);

        $view->vars['captcha_image'] = $captcha_image;
        $view->vars['captcha_width'] = $width;
        $view->vars['captcha_height'] = $height;
        $view->vars['captcha_token'] = $captcha_token;
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('width', 200);
        $resolver->setDefault('height', 80);
        $resolver->setDefault('char_count', 5);
        $resolver->setDefault('char_pool', '123456789ABCDEFGHJKLMNOPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz');
        $resolver->setDefault('max_deg', 10);
        $resolver->setDefault('spacing', 4);
        $resolver->setDefault('max_x_offset', 2);
        $resolver->setDefault('max_y_offset', 4);
        $resolver->setDefault('font', $this->project_dir . '/fonts/ubuntu_subset.ttf');
        $resolver->setRequired('width');
        $resolver->setRequired('height');
        $resolver->setRequired('char_count');
        $resolver->setRequired('char_pool');
        $resolver->setRequired('max_deg');
        $resolver->setRequired('spacing');
        $resolver->setRequired('max_x_offset');
        $resolver->setRequired('max_y_offset');
        $resolver->setRequired('font');
        $resolver->setAllowedTypes('width', 'int');
        $resolver->setAllowedTypes('height', 'int');
        $resolver->setAllowedTypes('char_count', 'int');
        $resolver->setAllowedTypes('char_pool', 'string');
        $resolver->setAllowedTypes('max_deg', 'int');
        $resolver->setAllowedTypes('spacing', 'int');
        $resolver->setAllowedTypes('max_x_offset', 'int');
        $resolver->setAllowedTypes('max_y_offset', 'int');
        $resolver->setAllowedTypes('font', 'string');
    }

    public function getParent():string
    {
        return TextType::class;
    }

    public function getBlockPrefix():string
    {
        return 'captcha';
    }
}