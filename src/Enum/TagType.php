<?php declare(strict_types=1);

namespace App\Enum;

enum TagType:string
{
    case Users = 'users';
    case Messages = 'messages';
    case Calendar = 'calendar';
    case News = 'news';
    case Docs = 'docs';
    case Transactions = 'transactions';
    case ForumTopics = 'forum_topics';
    case Blog = 'blog';

    public static function values(): array
    {
       return array_column(self::cases(), 'value');
    }
}
