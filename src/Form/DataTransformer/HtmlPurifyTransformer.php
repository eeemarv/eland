<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Component\Form\DataTransformerInterface;

class HtmlPurifyTransformer implements DataTransformerInterface
{
    protected $html_purifier;

    public function __construct(
        HtmlPurifier $html_purifier
    )
    {
        $this->html_purifier = $html_purifier;
    }

    public function transform($content)
    {
        if (null === $content)
        {
            return '';
        }

        return $content;
    }

    public function reverseTransform($content)
    {
        return $this->html_purifier->purify($content);
    }
}