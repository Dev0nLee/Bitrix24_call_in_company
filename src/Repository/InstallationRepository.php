<?php

namespace App\Repository;

use App\Entity\Installation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Installation>
 */
class InstallationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Installation::class);
    }

    public function save(Installation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function removeByDomain(string $domain): void
    {
        $qb = $this->createQueryBuilder('i')
            ->delete()
            ->where('i.domain = :domain')
            ->setParameter('domain', $domain);
        $qb->getQuery()->execute();
    }
}