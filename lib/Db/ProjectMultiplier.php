<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Entity for project-specific employee category multipliers.
 * 
 * Employment types are defined in EmployeeSettings::EMPLOYMENT_TYPES
 * 
 * @method int getId()
 * @method void setId(int $id)
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getEmploymentType()
 * @method void setEmploymentType(string $employmentType)
 * @method float getMultiplier()
 * @method void setMultiplier(float $multiplier)
 */
class ProjectMultiplier extends Entity implements JsonSerializable {
    protected $projectId;
    protected $employmentType;
    protected $multiplier;

    public function __construct() {
        $this->addType('projectId', 'integer');
        $this->addType('multiplier', 'float');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'projectId' => $this->getProjectId(),
            'employmentType' => $this->getEmploymentType(),
            'multiplier' => $this->getMultiplier(),
        ];
    }
}
