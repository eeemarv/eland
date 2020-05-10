<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Queue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class QueueRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Queue::class);
    }

    /*
    public function findBySomething($value)
    {
        return $this->createQueryBuilder('q')
            ->where('q.something = :value')->setParameter('value', $value)
            ->orderBy('q.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */
}
