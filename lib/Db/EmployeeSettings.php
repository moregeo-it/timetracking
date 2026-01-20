<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getEmploymentType()
 * @method void setEmploymentType(string $employmentType)
 * @method float getWeeklyHours()
 * @method void setWeeklyHours(float $weeklyHours)
 * @method float|null getMaxTotalHours()
 * @method void setMaxTotalHours(?float $maxTotalHours)
 * @method int getVacationDaysPerYear()
 * @method void setVacationDaysPerYear(int $vacationDaysPerYear)
 * @method float|null getHourlyRate()
 * @method void setHourlyRate(?float $hourlyRate)
 * @method \DateTime|null getEmploymentStart()
 * @method void setEmploymentStart(?\DateTime $employmentStart)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class EmployeeSettings extends Entity implements JsonSerializable {
    protected $userId;
    protected $employmentType; // 'director', 'contract', 'freelance', 'student'
    protected $weeklyHours;
    protected $maxTotalHours;
    protected $vacationDaysPerYear;
    protected $hourlyRate;
    protected $employmentStart;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('weeklyHours', 'float');
        $this->addType('maxTotalHours', 'float');
        $this->addType('vacationDaysPerYear', 'integer');
        $this->addType('hourlyRate', 'float');
        $this->addType('employmentStart', 'datetime');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'userId' => $this->getUserId(),
            'employmentType' => $this->getEmploymentType(),
            'weeklyHours' => $this->getWeeklyHours(),
            'maxTotalHours' => $this->getMaxTotalHours(),
            'vacationDaysPerYear' => $this->getVacationDaysPerYear(),
            'hourlyRate' => $this->getHourlyRate(),
            'employmentStart' => $this->getEmploymentStart()?->format('Y-m-d'),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}

