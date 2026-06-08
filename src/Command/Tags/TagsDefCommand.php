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
  public mixed $id;

  #[Type(type: 'string')]
  public mixed $tag_type;

  #[Sequentially(constraints:[
    new NotBlank(),
    new Type(type: 'string'),
    new Length(max: 12),
  ])]
  public mixed $txt;

  #[Type(type: 'string')]
  public mixed $description;

  #[Sequentially(constraints:[
    new NotBlank(),
    new CssColor(formats: CssColor::HEX_LONG),
  ])]
  public mixed $txt_color;

  #[Sequentially(constraints:[
    new NotBlank(),
    new CssColor(formats: CssColor::HEX_LONG),
  ])]
  public mixed $bg_color;
}
