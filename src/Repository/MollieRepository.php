<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MollieRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function get_payment_by_token(
		string $token,
		string $schema
	):array|false
	{
		return $this->db->fetchAssociative('select p.*, r.description, u.code
			from ' . $schema . '.mollie_payments p,
				' . $schema . '.mollie_payment_requests r,
				' . $schema . '.users u
			where p.request_id = r.id
				and u.id = p.user_id
				and p.token = ?',
			[$token], [\PDO::PARAM_STR]);
	}

	public function update_mollie_payment_id(
		string $token,
		string $mollie_payment_id,
		string $schema
	):void
	{
		$this->db->update($schema . '.mollie_payments', [
			'mollie_payment_id' => $mollie_payment_id,
		], ['token' => $token]);
	}

	public function set_paid_by_token(
		string $token,
		string $mollie_status,
		string $schema
	):void
	{
		$this->db->update($schema . '.mollie_payments',[
			'mollie_status'     => $mollie_status,
			'is_paid'           => 't',
		], ['token' => $token], [\PDO::PARAM_STR]);
	}

	public function get_open_payments_for_user(
		int $user_id,
		string $schema
	):array
	{
		return $this->db->fetchAllAssociative('select p.amount, p.token, r.description
			from ' . $schema . '.mollie_payments p,
				' . $schema . '.mollie_payment_requests r
			where p.request_id = r.id
				and user_id = ?
				and is_canceled = \'f\'::bool
				and is_paid = \'f\'::bool',
			[$user_id], [\PDO::PARAM_INT]);
	}




}
