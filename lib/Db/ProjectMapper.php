<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ProjectMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'tt_projects', Project::class);
    }

    public function find(int $id): Project {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    public function findAll(bool $activeOnly = false): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('name', 'ASC');
        
        if ($activeOnly) {
            $qb->where($qb->expr()->eq('active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
        }
        
        return $this->findEntities($qb);
    }

    public function findByCustomer(int $customerId, bool $activeOnly = false): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('customer_id', $qb->createNamedParameter($customerId, IQueryBuilder::PARAM_INT)))
            ->orderBy('name', 'ASC');
        
        if ($activeOnly) {
            $qb->andWhere($qb->expr()->eq('active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
        }
        
        return $this->findEntities($qb);
    }
}

