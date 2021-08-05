<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Component\Form\DataTransformerInterface;

class HtmlPurifyTransformer implements DataTransformerInterface
{
    public function __construct(
        protected HtmlPurifier $html_purifier
    )
    {
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
        if (null === $content)
        {
            return '';
        }

        return $this->html_purifier->purify($content);
    }
}