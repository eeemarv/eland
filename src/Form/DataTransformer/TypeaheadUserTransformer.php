<?php declare(strict_types=1);

namespace App\Form\DataTransformer;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HttpFoundation\RequestStack;

class TypeaheadUserTransformer implements DataTransformerInterface
{
    private $db;
    private $schema;

    public function __construct(Db $db, RequestStack $requestStack)
    {
        $this->db = $db;
        $request = $requestStack->getCurrentRequest();
        $this->schema = $request->attributes->get('schema');
    }

    /*
    * from db to input (id to code + username)
    */
    public function transform($id)
    {
        if (null === $id)
        {
            return '';
        }

        $user = $this->db->fetchAssoc('select letscode, name
            from ' . $this->schema . '.users
             where id = ?', [$id]);

        if (!$user)
        {
            return '';
        }

        return $user['letscode'] . ' ' . $user['code'];
    }

    /*
    * from input to db (code to id)
    */
    public function reverseTransform($code)
    {
        if (!$code)
        {
            return;
        }

        list($code) = explode(' ', $code);

        $id = $this->db->fetchColumn('select id
            from ' . $this->schema . '.users
            where letscode = ?', [$code]);

        if (!$id)
        {
            return;
        }

        return $id;
    }
}