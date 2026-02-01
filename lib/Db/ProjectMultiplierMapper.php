<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use OCA\TimeTracking\Db\EmployeeSettings;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ProjectMultiplierMapper extends QBMapper {
    private DefaultMultiplierMapper $defaultMultiplierMapper;

    public function __construct(IDBConnection $db, DefaultMultiplierMapper $defaultMultiplierMapper) {
        parent::__construct($db, 'tt_project_multipliers', ProjectMultiplier::class);
        $this->defaultMultiplierMapper = $defaultMultiplierMapper;
    }

    public function find(int $id): ProjectMultiplier {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Find all multipliers for a project
     * 
     * @param int $projectId Project ID
     * @return ProjectMultiplier[]
     */
    public function findByProject(int $projectId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }

    /**
     * Find multiplier for a specific project and employment type
     * 
     * @param int $projectId Project ID
     * @param string $employmentType Employment type
     * @return ProjectMultiplier|null
     */
    public function findByProjectAndType(int $projectId, string $employmentType): ?ProjectMultiplier {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('employment_type', $qb->createNamedParameter($employmentType)));
        
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Get multiplier value for a project and employment type.
     * Returns project-specific value if set, otherwise the default multiplier.
     * 
     * @param int $projectId Project ID
     * @param string $employmentType Employment type
     * @return float Multiplier value
     */
    public function getMultiplierValue(int $projectId, string $employmentType): float {
        $multiplier = $this->findByProjectAndType($projectId, $employmentType);
        if ($multiplier) {
            return $multiplier->getMultiplier();
        }
        // Fall back to default multiplier
        return $this->defaultMultiplierMapper->getDefaultValue($employmentType);
    }

    /**
     * Get all multipliers for a project as an associative array.
     * Missing employment types will use default values.
     * 
     * @param int $projectId Project ID
     * @return array<string, float|null> Array with employment type as key and multiplier as value (null = uses default)
     */
    public function getMultipliersAsArray(int $projectId): array {
        $result = [];
        
        // Get project-specific multipliers
        $multipliers = $this->findByProject($projectId);
        $projectValues = [];
        foreach ($multipliers as $multiplier) {
            $projectValues[$multiplier->getEmploymentType()] = $multiplier->getMultiplier();
        }
        
        // Return only set values (null for defaults)
        foreach (EmployeeSettings::EMPLOYMENT_TYPES as $type) {
            $result[$type] = $projectValues[$type] ?? null;
        }
        
        return $result;
    }

    /**
     * Get effective multipliers for a project (including defaults).
     * 
     * @param int $projectId Project ID
     * @return array<string, float> Array with employment type as key and effective multiplier as value
     */
    public function getEffectiveMultipliersAsArray(int $projectId): array {
        $result = $this->defaultMultiplierMapper->getDefaultsAsArray();
        
        // Override with project-specific values
        $multipliers = $this->findByProject($projectId);
        foreach ($multipliers as $multiplier) {
            $result[$multiplier->getEmploymentType()] = $multiplier->getMultiplier();
        }
        
        return $result;
    }

    /**
     * Set or update multiplier for a project and employment type.
     * 
     * @param int $projectId Project ID
     * @param string $employmentType Employment type
     * @param float $value Multiplier value
     * @return ProjectMultiplier
     */
    public function setMultiplier(int $projectId, string $employmentType, float $value): ProjectMultiplier {
        // Validate multiplier value
        $value = max(0.01, min(2.0, $value));
        
        $existing = $this->findByProjectAndType($projectId, $employmentType);
        
        if ($existing) {
            $existing->setMultiplier($value);
            return $this->update($existing);
        } else {
            $multiplier = new ProjectMultiplier();
            $multiplier->setProjectId($projectId);
            $multiplier->setEmploymentType($employmentType);
            $multiplier->setMultiplier($value);
            return $this->insert($multiplier);
        }
    }

    /**
     * Set multiple multipliers for a project at once.
     * Only stores values that differ from the default.
     * If a value matches the default, any existing override is removed.
     * 
     * @param int $projectId Project ID
     * @param array<string, float|null> $multipliers Array with employment type as key and multiplier as value (null = use default)
     */
    public function setMultipliers(int $projectId, array $multipliers): void {
        $defaults = $this->defaultMultiplierMapper->getDefaultsAsArray();
        
        foreach ($multipliers as $employmentType => $value) {
            if (!in_array($employmentType, EmployeeSettings::EMPLOYMENT_TYPES, true)) {
                continue;
            }
            
            $existing = $this->findByProjectAndType($projectId, $employmentType);
            $defaultValue = $defaults[$employmentType] ?? 1.0;
            
            // If value is null, empty, or matches default, remove any override
            if ($value === null || $value === '' || abs((float)$value - $defaultValue) < 0.0001) {
                if ($existing) {
                    $this->delete($existing);
                }
            } else {
                // Store the override
                $value = max(0.01, min(2.0, (float)$value));
                if ($existing) {
                    $existing->setMultiplier($value);
                    $this->update($existing);
                } else {
                    $multiplier = new ProjectMultiplier();
                    $multiplier->setProjectId($projectId);
                    $multiplier->setEmploymentType($employmentType);
                    $multiplier->setMultiplier($value);
                    $this->insert($multiplier);
                }
            }
        }
    }

    /**
     * Delete all multipliers for a project.
     * 
     * @param int $projectId Project ID
     */
    public function deleteByProject(int $projectId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
