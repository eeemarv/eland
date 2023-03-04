<?php declare(strict_types=1);

namespace App\Command\Tags;

use App\Command\CommandInterface;
use App\Validator\Tag\TagUniqueTxt;
use Symfony\Component\Validator\Constraints\CssColor;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

#[TagUniqueTxt(groups: ['unique_txt'])]
#[GroupSequence(groups: ['TagsDefCommand', 'unique_txt'])]
class TagsDefCommand implements CommandInterface
{
    #[Type(type: 'int')]
    public $id;

    #[Type(type: 'string')]
    public $tag_type;

    #[Sequentially(constraints:[
        new NotBlank(),
        new Type(type: 'string'),
        new Length(max: 12),
    ])]
    public $txt;

    #[Type(type: 'string')]
    public $description;

    #[Sequentially(constraints:[
        new NotBlank(),
        new CssColor(formats: CssColor::HEX_LONG)
    ])]
    public $txt_color;

    #[Sequentially(constraints:[
        new NotBlank(),
        new CssColor(formats: CssColor::HEX_LONG)
    ])]
    public $bg_color;
}
