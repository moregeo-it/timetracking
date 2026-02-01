<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Entity for default employee category multipliers.
 * These are system-wide defaults used when no project-specific multiplier is set.
 * 
 * Employment types are defined in EmployeeSettings::EMPLOYMENT_TYPES
 * 
 * @method int getId()
 * @method void setId(int $id)
 * @method string getEmploymentType()
 * @method void setEmploymentType(string $employmentType)
 * @method float getMultiplier()
 * @method void setMultiplier(float $multiplier)
 */
class DefaultMultiplier extends Entity implements JsonSerializable {
    protected $employmentType;
    protected $multiplier;

    public function __construct() {
        $this->addType('multiplier', 'float');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'employmentType' => $this->getEmploymentType(),
            'multiplier' => $this->getMultiplier(),
        ];
    }
}
