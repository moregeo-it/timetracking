<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class EmployeeSettingsMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'tt_emp_settings', EmployeeSettings::class);
    }

    public function find(int $id): EmployeeSettings {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    public function findByUserId(string $userId): ?EmployeeSettings {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('user_id', 'ASC');
        return $this->findEntities($qb);
    }
}

