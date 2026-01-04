<?php

declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use DateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class VacationMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'tt_vacations', Vacation::class);
    }

    /**
     * Find a vacation by ID
     */
    public function find(int $id): Vacation {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        
        return $this->findEntity($qb);
    }

    /**
     * Find all vacations for a user
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
     * Find all vacations within a date range
     */
    public function findByDateRange(string $userId, DateTime $startDate, DateTime $endDate): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere(
                $qb->expr()->orX(
                    // Vacation starts within range
                    $qb->expr()->andX(
                        $qb->expr()->gte('start_date', $qb->createNamedParameter($startDate->format('Y-m-d'))),
                        $qb->expr()->lte('start_date', $qb->createNamedParameter($endDate->format('Y-m-d')))
                    ),
                    // Vacation ends within range
                    $qb->expr()->andX(
                        $qb->expr()->gte('end_date', $qb->createNamedParameter($startDate->format('Y-m-d'))),
                        $qb->expr()->lte('end_date', $qb->createNamedParameter($endDate->format('Y-m-d')))
                    ),
                    // Vacation spans entire range
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
     * Find all vacations by status
     */
    public function findByStatus(string $status): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter($status)))
            ->orderBy('start_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Calculate total vacation days used by user in a year
     */
    public function getTotalDaysUsed(string $userId, int $year): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->sum('days'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('approved')))
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

        $result = $qb->execute();
        $row = $result->fetch();
        $result->closeCursor();

        return (int)($row['sum'] ?? 0);
    }
}

