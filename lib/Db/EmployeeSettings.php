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
 * @method \DateTime|null getValidFrom()
 * @method void setValidFrom(?\DateTime $validFrom)
 * @method \DateTime|null getValidTo()
 * @method void setValidTo(?\DateTime $validTo)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 * @method int|null getSickNoteRequiredDay()
 * @method void setSickNoteRequiredDay(?int $sickNoteRequiredDay)
 */
class EmployeeSettings extends Entity implements JsonSerializable {
    protected $userId;
    protected $employmentType;
    protected $weeklyHours;
    protected $maxTotalHours;
    protected $vacationDaysPerYear;
    protected $hourlyRate;
    protected $employmentStart;
    protected $validFrom;  // Date when these settings become effective
    protected $validTo;    // Date when these settings end (null = current/indefinite)
    protected $sickNoteRequiredDay; // Day of illness when AU is required (EFZG §5, default 4)
    protected $createdAt;
    protected $updatedAt;

    /**
     * Available employment types
     * - director:  Geschäftsführer (exempt from labor law checks)
     * - contract:  Festanstellung / Teilzeit (regular employees)
     * - freelance: Freiberufler / Stundenkontingent (hour quota, no vacation)
     * - intern:    Praktikant (hour quota, no vacation)
     * - student:   Werkstudent (reduced hours)
     */
    public const EMPLOYMENT_TYPES = [
        'director',
        'contract',
        'freelance',
        'intern',
        'student',
    ];

    public function __construct() {
        $this->addType('weeklyHours', 'float');
        $this->addType('maxTotalHours', 'float');
        $this->addType('vacationDaysPerYear', 'integer');
        $this->addType('hourlyRate', 'float');
        $this->addType('sickNoteRequiredDay', 'integer');
        $this->addType('employmentStart', 'datetime');
        $this->addType('validFrom', 'datetime');
        $this->addType('validTo', 'datetime');
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
            'sickNoteRequiredDay' => $this->getSickNoteRequiredDay() ?? 4,
            'employmentStart' => $this->getEmploymentStart()?->format('Y-m-d'),
            'validFrom' => $this->getValidFrom()?->format('Y-m-d'),
            'validTo' => $this->getValidTo()?->format('Y-m-d'),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}

