<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use DateTime;
use DateTimeZone;
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

    /**
     * Find entries by user within a date range
     * 
     * @param string $userId User ID
     * @param int|null $startTimestamp Unix timestamp for start of range (inclusive)
     * @param int|null $endTimestamp Unix timestamp for end of range (inclusive)
     * @return TimeEntry[]
     */
    public function findByUser(string $userId, ?int $startTimestamp = null, ?int $endTimestamp = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('start_timestamp', 'DESC');
        
        if ($startTimestamp !== null) {
            $qb->andWhere($qb->expr()->gte('start_timestamp', $qb->createNamedParameter($startTimestamp, IQueryBuilder::PARAM_INT)));
        }
        if ($endTimestamp !== null) {
            $qb->andWhere($qb->expr()->lte('start_timestamp', $qb->createNamedParameter($endTimestamp, IQueryBuilder::PARAM_INT)));
        }
        
        return $this->findEntities($qb);
    }

    /**
     * Find entries by project within a date range
     * 
     * @param int $projectId Project ID
     * @param int|null $startTimestamp Unix timestamp for start of range (inclusive)
     * @param int|null $endTimestamp Unix timestamp for end of range (inclusive)
     * @return TimeEntry[]
     */
    public function findByProject(int $projectId, ?int $startTimestamp = null, ?int $endTimestamp = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->orderBy('start_timestamp', 'DESC');
        
        if ($startTimestamp !== null) {
            $qb->andWhere($qb->expr()->gte('start_timestamp', $qb->createNamedParameter($startTimestamp, IQueryBuilder::PARAM_INT)));
        }
        if ($endTimestamp !== null) {
            $qb->andWhere($qb->expr()->lte('start_timestamp', $qb->createNamedParameter($endTimestamp, IQueryBuilder::PARAM_INT)));
        }
        
        return $this->findEntities($qb);
    }

    public function findRunningTimer(string $userId): ?TimeEntry {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->isNull('end_timestamp'))
            ->orderBy('start_timestamp', 'DESC')
            ->setMaxResults(1);
        
        try {
            return $this->findEntity($qb);
        } catch (\Exception $e) {
            return null;
        }
    }
}

