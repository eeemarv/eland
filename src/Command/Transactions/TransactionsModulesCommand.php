<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsModulesCommand implements CommandInterface
{
    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'transactions.fields.service_stuff.enabled')]
    public $service_stuff_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'accounts.limits.enabled')]
    public $limits_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'accounts.limits.auto_min.enabled')]
    public $autominlimit_enabled;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'transactions.mass.enabled')]
    public $mass_enabled;
}
