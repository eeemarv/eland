<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryRepository
{
	protected Db $db;

	public function __construct(Db $db)
	{
		$this->db = $db;
    }

    public function get(int $id, string $schema):array
    {
        $category = $this->db->fetchAssoc('select c.*, cp.name as parent_name
            from ' . $schema . '.categories c
            left join ' . $schema . '.categories cp
                on c.parent_id = cp.id
            where c.id = ?', [$id], [\PDO::PARAM_INT]);

        if (!$category)
        {
            throw new NotFoundHttpException('Category ' . $id . ' not found.');
        }

        return $category;
    }
}
