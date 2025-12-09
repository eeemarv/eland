<?php declare(strict_types=1);

namespace App\Service;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\AutoMinLimitService;
use App\Service\ConfigService;
use App\Render\AccountRender;
use App\Repository\AccountRepository;

class TransactionService
{
	public function __construct(
		protected Db $db,
		protected AccountRepository $account_repository,
		protected LoggerInterface $logger,
		protected AutoMinLimitService $autominlimit_service,
		protected AutoDeactivateService $auto_deactivate_service,
		protected ConfigService $config_service,
		protected AccountRender $account_render
	)
	{
	}

	public function generate_transid(
    int $s_id,
    string $system_name
  ):string
	{
		$transid = substr(sha1(random_bytes(16)), 0, 12);
		$transid .= '_';
		$transid .= (string) $s_id;
		$transid .= '@' . $system_name;
		return $transid;
	}

	public function insert(
    array $transaction,
    Schema $schema
  ):int
	{
		$from_id = (int) $transaction['id_from'];
		$to_id = (int) $transaction['id_to'];
		$amount = (int) $transaction['amount'];

		$this->db->beginTransaction();

		$this->db->insert($schema->str() . '.transactions', $transaction);
		$id = (int) $this->db->lastInsertId($schema->str() . '.transactions_id_seq');
		$this->account_repository->update_balance(
      account_id: $to_id,
      amount: $amount,
      schema: $schema
    );
		$this->account_repository->update_balance(
      account_id: $from_id,
      amount: -$amount,
      schema: $schema
    );
		$this->db->commit();

		$this->autominlimit_service->process(
			$from_id,
			$to_id,
			$amount,
			$schema->str()
		);

		$this->auto_deactivate_service->process($to_id, $schema->str());
		$this->auto_deactivate_service->process($from_id, $schema->str());

		$this->logger->info('Transaction ' . $transaction['transid'] . ' saved: ' .
			$amount . ' ' .
			$this->config_service->get_str('transactions.currency.name', $schema) .
			' from user ' .
			$this->account_render->str_id($from_id, $schema->str()) .
			' to user ' .
			$this->account_render->str_id($to_id, $schema->str()),
			['schema' => $schema]);

		return $id;
	}
}
