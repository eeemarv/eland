<?php declare(strict_types=1);

namespace App\Command\News;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class NewsSortCommand implements CommandInterface
{
    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'news.sort.asc')]
    public $sort_asc;
}
