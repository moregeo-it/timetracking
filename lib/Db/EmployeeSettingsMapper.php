<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use DateTime;
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

    /**
     * Find the current (active) settings for a user.
     * Current settings are those where:
     * - valid_to is NULL (no end date), or
     * - valid_to >= today AND valid_from <= today (or valid_from is NULL)
     */
    public function findByUserId(string $userId): ?EmployeeSettings {
        return $this->findByUserIdAtDate($userId, new DateTime());
    }

    /**
     * Find settings for a user that are valid at a specific date.
     * 
     * @param string $userId The user ID
     * @param DateTime $date The date to check
     * @return EmployeeSettings|null Settings valid at that date, or null if none found
     */
    public function findByUserIdAtDate(string $userId, DateTime $date): ?EmployeeSettings {
        $dateStr = $date->format('Y-m-d');
        
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('valid_from'),
                    $qb->expr()->lte('valid_from', $qb->createNamedParameter($dateStr))
                )
            )
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('valid_to'),
                    $qb->expr()->gte('valid_to', $qb->createNamedParameter($dateStr))
                )
            )
            ->orderBy('valid_from', 'DESC')
            ->setMaxResults(1);
        
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Find all settings periods for a user, ordered by valid_from descending (newest first).
     * 
     * @param string $userId The user ID
     * @return array Array of EmployeeSettings objects
     */
    public function findAllByUserId(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('valid_from', 'DESC');
        return $this->findEntities($qb);
    }

    /**
     * Find all settings that overlap with a date range for a user.
     * This is useful for reports that span multiple periods.
     * 
     * @param string $userId The user ID
     * @param DateTime $startDate Start of the date range
     * @param DateTime $endDate End of the date range
     * @return array Array of EmployeeSettings objects ordered by valid_from
     */
    public function findByUserIdInRange(string $userId, DateTime $startDate, DateTime $endDate): array {
        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');
        
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            // Settings that started before or during the range
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('valid_from'),
                    $qb->expr()->lte('valid_from', $qb->createNamedParameter($endStr))
                )
            )
            // Settings that end after or during the range (or don't have an end date)
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('valid_to'),
                    $qb->expr()->gte('valid_to', $qb->createNamedParameter($startStr))
                )
            )
            ->orderBy('valid_from', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Find all employees with their current settings.
     * Returns one settings record per user (the currently active one).
     */
    public function findAll(): array {
        $today = (new DateTime())->format('Y-m-d');
        
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->isNull('valid_from'),
                    $qb->expr()->lte('valid_from', $qb->createNamedParameter($today))
                )
            )
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('valid_to'),
                    $qb->expr()->gte('valid_to', $qb->createNamedParameter($today))
                )
            )
            ->orderBy('user_id', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Check if there's an overlapping period for a user when creating/updating settings.
     * 
     * @param string $userId The user ID
     * @param DateTime|null $validFrom Start date of the period
     * @param DateTime|null $validTo End date of the period (null for ongoing)
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool True if there's an overlap
     */
    public function hasOverlappingPeriod(string $userId, ?DateTime $validFrom, ?DateTime $validTo, ?int $excludeId = null): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        
        if ($excludeId !== null) {
            $qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeId, IQueryBuilder::PARAM_INT)));
        }
        
        // Check for overlap:
        // New period: [validFrom, validTo]
        // Existing period: [existing_from, existing_to]
        // Overlap occurs when: new_from <= existing_to AND new_to >= existing_from
        
        $validFromStr = $validFrom ? $validFrom->format('Y-m-d') : null;
        $validToStr = $validTo ? $validTo->format('Y-m-d') : null;
        
        // New period start is before or at existing end (or existing has no end)
        $startCondition = $qb->expr()->orX(
            $qb->expr()->isNull('valid_to')
        );
        if ($validFromStr !== null) {
            $startCondition->add($qb->expr()->gte('valid_to', $qb->createNamedParameter($validFromStr)));
        }
        
        // New period end is after or at existing start (or existing has no start, or new period has no end)
        $endCondition = $qb->expr()->orX(
            $qb->expr()->isNull('valid_from')
        );
        if ($validToStr !== null) {
            $endCondition->add($qb->expr()->lte('valid_from', $qb->createNamedParameter($validToStr)));
        } else {
            // If new period has no end date, it overlaps with anything that doesn't have an end date
            // or anything that ends after the new period starts
            $endCondition = $qb->expr()->orX(
                $qb->expr()->isNull('valid_from'),
                $qb->expr()->eq('1', '1') // Always true - open-ended period can overlap with anything
            );
        }
        
        $qb->andWhere($startCondition)
           ->andWhere($endCondition);
        
        $result = $qb->executeQuery();
        $count = (int)$result->fetchOne();
        $result->closeCursor();
        
        return $count > 0;
    }

    /**
     * Close the previous open-ended period when creating a new one.
     * Sets valid_to to the day before the new period starts.
     * 
     * @param string $userId The user ID
     * @param DateTime $newPeriodStart Start date of the new period
     * @return void
     */
    public function closePreviousPeriod(string $userId, DateTime $newPeriodStart): void {
        $previousDay = clone $newPeriodStart;
        $previousDay->modify('-1 day');
        
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('valid_to', $qb->createNamedParameter($previousDay->format('Y-m-d')))
            ->set('updated_at', $qb->createNamedParameter((new DateTime())->format('Y-m-d H:i:s')))
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->isNull('valid_to'));
        $qb->executeStatement();
    }
}

