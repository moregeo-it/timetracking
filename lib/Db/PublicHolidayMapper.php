<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use DateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;

class PublicHolidayMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'tt_public_holidays', PublicHoliday::class);
    }

    /**
     * Find a public holiday by ID
     */
    public function find(int $id): PublicHoliday {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        
        return $this->findEntity($qb);
    }

    /**
     * Find all public holidays
     */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find all public holidays for a specific year
     */
    public function findByYear(int $year): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->gte('date', $qb->createNamedParameter("$year-01-01")))
            ->andWhere($qb->expr()->lte('date', $qb->createNamedParameter("$year-12-31")))
            ->orderBy('date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find public holidays within a date range
     */
    public function findByDateRange(DateTime $startDate, DateTime $endDate): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->gte('date', $qb->createNamedParameter($startDate->format('Y-m-d'))))
            ->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($endDate->format('Y-m-d'))))
            ->orderBy('date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Check if a holiday exists for a specific date
     */
    public function existsByDate(DateTime $date): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*) as cnt'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('date', $qb->createNamedParameter($date->format('Y-m-d'))));

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return (int)($row['cnt'] ?? 0) > 0;
    }

    /**
     * Check if a specific date is a public holiday
     */
    public function isHoliday(DateTime $date): bool {
        return $this->existsByDate($date);
    }

    /**
     * Get holidays for a given date (returns all matching holidays)
     */
    public function getHolidaysForDate(DateTime $date): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('date', $qb->createNamedParameter($date->format('Y-m-d'))));

        return $this->findEntities($qb);
    }

    /**
     * Count public holidays in a date range
     */
    public function countHolidaysInRange(DateTime $startDate, DateTime $endDate): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*) as cnt'))
            ->from($this->getTableName())
            ->where($qb->expr()->gte('date', $qb->createNamedParameter($startDate->format('Y-m-d'))))
            ->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($endDate->format('Y-m-d'))));

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return (int)($row['cnt'] ?? 0);
    }
}
