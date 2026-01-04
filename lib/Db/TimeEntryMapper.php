<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use DateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TimeEntryMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'tt_entries', TimeEntry::class);
    }

    public function find(int $id): TimeEntry {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    public function findByUser(string $userId, ?DateTime $startDate = null, ?DateTime $endDate = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('date', 'DESC')
            ->addOrderBy('start_time', 'DESC');
        
        if ($startDate) {
            $qb->andWhere($qb->expr()->gte('date', $qb->createNamedParameter($startDate->format('Y-m-d'), IQueryBuilder::PARAM_STR)));
        }
        if ($endDate) {
            $qb->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($endDate->format('Y-m-d'), IQueryBuilder::PARAM_STR)));
        }
        
        return $this->findEntities($qb);
    }

    public function findByProject(int $projectId, ?DateTime $startDate = null, ?DateTime $endDate = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->orderBy('date', 'DESC')
            ->addOrderBy('start_time', 'DESC');
        
        if ($startDate) {
            $qb->andWhere($qb->expr()->gte('date', $qb->createNamedParameter($startDate->format('Y-m-d'), IQueryBuilder::PARAM_STR)));
        }
        if ($endDate) {
            $qb->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($endDate->format('Y-m-d'), IQueryBuilder::PARAM_STR)));
        }
        
        return $this->findEntities($qb);
    }

    public function findRunningTimer(string $userId): ?TimeEntry {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->isNull('end_time'))
            ->orderBy('start_time', 'DESC')
            ->setMaxResults(1);
        
        try {
            return $this->findEntity($qb);
        } catch (\Exception $e) {
            return null;
        }
    }
}

