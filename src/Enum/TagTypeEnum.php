<?php declare(strict_types=1);

namespace App\Enum;

enum TagTypeEnum:string
{
    case USERS = 'users';
    case MESSAGES = 'messages';
    case CALENDAR = 'calendar';
    case NEWS = 'news';
    case DOCS = 'docs';
    case TRANSACTIONS = 'transactions';
    case FORUM_TOPICS = 'forum_topics';
    case BLOG = 'blog';

    public static function values(): array
    {
       return array_column(self::cases(), 'value');
    }
}
