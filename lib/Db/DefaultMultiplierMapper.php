<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use OCA\TimeTracking\Db\EmployeeSettings;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DefaultMultiplierMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'tt_default_multipliers', DefaultMultiplier::class);
    }

    public function find(int $id): DefaultMultiplier {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Find all default multipliers
     * 
     * @return DefaultMultiplier[]
     */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName());
        return $this->findEntities($qb);
    }

    /**
     * Find default multiplier for a specific employment type
     * 
     * @param string $employmentType Employment type
     * @return DefaultMultiplier|null
     */
    public function findByType(string $employmentType): ?DefaultMultiplier {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('employment_type', $qb->createNamedParameter($employmentType)));
        
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Get default multiplier value for an employment type.
     * Returns 1.0 if no default is set.
     * 
     * @param string $employmentType Employment type
     * @return float Multiplier value (default 1.0)
     */
    public function getDefaultValue(string $employmentType): float {
        $multiplier = $this->findByType($employmentType);
        return $multiplier ? $multiplier->getMultiplier() : 1.0;
    }

    /**
     * Get all default multipliers as an associative array.
     * Missing employment types will have value 1.0.
     * 
     * @return array<string, float> Array with employment type as key and multiplier as value
     */
    public function getDefaultsAsArray(): array {
        $result = [];
        
        // Initialize with 1.0 as fallback
        foreach (EmployeeSettings::EMPLOYMENT_TYPES as $type) {
            $result[$type] = 1.0;
        }
        
        // Override with stored values
        $multipliers = $this->findAll();
        foreach ($multipliers as $multiplier) {
            $result[$multiplier->getEmploymentType()] = $multiplier->getMultiplier();
        }
        
        return $result;
    }

    /**
     * Set or update default multiplier for an employment type.
     * 
     * @param string $employmentType Employment type
     * @param float $value Multiplier value
     * @return DefaultMultiplier
     */
    public function setDefault(string $employmentType, float $value): DefaultMultiplier {
        // Validate multiplier value
        $value = max(0.01, min(2.0, $value));
        
        $existing = $this->findByType($employmentType);
        
        if ($existing) {
            $existing->setMultiplier($value);
            return $this->update($existing);
        } else {
            $multiplier = new DefaultMultiplier();
            $multiplier->setEmploymentType($employmentType);
            $multiplier->setMultiplier($value);
            return $this->insert($multiplier);
        }
    }

    /**
     * Set multiple default multipliers at once.
     * 
     * @param array<string, float> $multipliers Array with employment type as key and multiplier as value
     */
    public function setDefaults(array $multipliers): void {
        foreach ($multipliers as $employmentType => $value) {
            if (in_array($employmentType, EmployeeSettings::EMPLOYMENT_TYPES, true)) {
                $this->setDefault($employmentType, $value);
            }
        }
    }

    /**
     * Delete default multiplier for an employment type.
     * 
     * @param string $employmentType Employment type
     */
    public function deleteByType(string $employmentType): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('employment_type', $qb->createNamedParameter($employmentType)));
        $qb->executeStatement();
    }
}
