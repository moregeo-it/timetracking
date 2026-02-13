<?php

declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use DateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SickDayMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'tt_sick_days', SickDay::class);
    }

    /**
     * Find a sick day entry by ID
     */
    public function find(int $id): SickDay {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        
        return $this->findEntity($qb);
    }

    /**
     * Find all sick days for a user, optionally filtered by year
     */
    public function findByUser(string $userId, ?int $year = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        if ($year !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        $qb->expr()->gte('start_date', $qb->createNamedParameter("$year-01-01")),
                        $qb->expr()->lte('start_date', $qb->createNamedParameter("$year-12-31"))
                    ),
                    $qb->expr()->andX(
                        $qb->expr()->gte('end_date', $qb->createNamedParameter("$year-01-01")),
                        $qb->expr()->lte('end_date', $qb->createNamedParameter("$year-12-31"))
                    )
                )
            );
        }

        $qb->orderBy('start_date', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Find all sick days within a date range for a user
     */
    public function findByDateRange(string $userId, DateTime $startDate, DateTime $endDate): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        $qb->expr()->gte('start_date', $qb->createNamedParameter($startDate->format('Y-m-d'))),
                        $qb->expr()->lte('start_date', $qb->createNamedParameter($endDate->format('Y-m-d')))
                    ),
                    $qb->expr()->andX(
                        $qb->expr()->gte('end_date', $qb->createNamedParameter($startDate->format('Y-m-d'))),
                        $qb->expr()->lte('end_date', $qb->createNamedParameter($endDate->format('Y-m-d')))
                    ),
                    $qb->expr()->andX(
                        $qb->expr()->lte('start_date', $qb->createNamedParameter($startDate->format('Y-m-d'))),
                        $qb->expr()->gte('end_date', $qb->createNamedParameter($endDate->format('Y-m-d')))
                    )
                )
            )
            ->orderBy('start_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find all sick days for all users, optionally filtered by year
     */
    public function findAll(?int $year = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName());

        if ($year !== null) {
            $qb->where(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        $qb->expr()->gte('start_date', $qb->createNamedParameter("$year-01-01")),
                        $qb->expr()->lte('start_date', $qb->createNamedParameter("$year-12-31"))
                    ),
                    $qb->expr()->andX(
                        $qb->expr()->gte('end_date', $qb->createNamedParameter("$year-01-01")),
                        $qb->expr()->lte('end_date', $qb->createNamedParameter("$year-12-31"))
                    )
                )
            );
        }

        $qb->orderBy('start_date', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Calculate total sick days for a user in a year
     */
    public function getTotalDaysUsed(string $userId, int $year): float {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COALESCE(SUM(days), 0) as total_days'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        $qb->expr()->gte('start_date', $qb->createNamedParameter("$year-01-01")),
                        $qb->expr()->lte('start_date', $qb->createNamedParameter("$year-12-31"))
                    ),
                    $qb->expr()->andX(
                        $qb->expr()->gte('end_date', $qb->createNamedParameter("$year-01-01")),
                        $qb->expr()->lte('end_date', $qb->createNamedParameter("$year-12-31"))
                    )
                )
            );

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return (float)($row['total_days'] ?? 0);
    }

    /**
     * Check if a sick day overlaps with existing sick days for the same user.
     */
    public function hasOverlappingSickDay(string $userId, DateTime $startDate, DateTime $endDate, ?int $excludeId = null): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*) as cnt'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->lt(
                $qb->createNamedParameter($startDate->format('Y-m-d')),
                'end_date'
            ))
            ->andWhere($qb->expr()->gt(
                $qb->createNamedParameter($endDate->format('Y-m-d')),
                'start_date'
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
