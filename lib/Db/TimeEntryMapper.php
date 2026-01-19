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

    /**
     * Check if a time entry overlaps with existing entries for the same user.
     * Entries can share the same end/start time (touching is allowed).
     * 
     * Overlap condition: entry1.start < entry2.end AND entry1.end > entry2.start
     * 
     * @param string $userId User ID
     * @param int $startTimestamp Start timestamp of the new entry
     * @param int $endTimestamp End timestamp of the new entry
     * @param int|null $excludeId ID of entry to exclude (for updates)
     * @return bool True if there is an overlap
     */
    public function hasOverlappingEntry(string $userId, int $startTimestamp, int $endTimestamp, ?int $excludeId = null): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*) as cnt'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->isNotNull('end_timestamp')) // Only check completed entries
            // Overlap: new.start < existing.end AND new.end > existing.start
            ->andWhere($qb->expr()->lt(
                $qb->createNamedParameter($startTimestamp, IQueryBuilder::PARAM_INT),
                'end_timestamp'
            ))
            ->andWhere($qb->expr()->gt(
                $qb->createNamedParameter($endTimestamp, IQueryBuilder::PARAM_INT),
                'start_timestamp'
            ));
        
        if ($excludeId !== null) {
            $qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeId, IQueryBuilder::PARAM_INT)));
        }
        
        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();
        
        return (int)($row['cnt'] ?? 0) > 0;
    }
}

