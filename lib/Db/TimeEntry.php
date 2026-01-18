<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method \DateTime getDate()
 * @method void setDate(\DateTime $date)
 * @method \DateTime getStartTime()
 * @method void setStartTime(\DateTime $startTime)
 * @method \DateTime|null getEndTime()
 * @method void setEndTime(?\DateTime $endTime)
 * @method int|null getDurationMinutes()
 * @method void setDurationMinutes(?int $durationMinutes)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method bool getBillable()
 * @method void setBillable(bool $billable)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class TimeEntry extends Entity implements JsonSerializable {
    protected $projectId;
    protected $userId;
    protected $date;
    protected $startTime;
    protected $endTime;
    protected $durationMinutes;
    protected $description;
    protected $billable;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('projectId', 'integer');
        $this->addType('date', 'datetime');
        $this->addType('startTime', 'datetime');
        $this->addType('endTime', 'datetime');
        $this->addType('durationMinutes', 'integer');
        $this->addType('billable', 'boolean');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        // Get the default timezone
        $tz = new \DateTimeZone(date_default_timezone_get());
        
        // Format start/end time with timezone offset for correct client-side parsing
        $startTime = $this->getStartTime();
        if ($startTime) {
            $startTime->setTimezone($tz);
        }
        $endTime = $this->getEndTime();
        if ($endTime) {
            $endTime->setTimezone($tz);
        }
        
        return [
            'id' => $this->getId(),
            'projectId' => $this->getProjectId(),
            'userId' => $this->getUserId(),
            'date' => $this->getDate()?->format('Y-m-d'),
            'startTime' => $startTime?->format('c'), // ISO 8601 with timezone
            'endTime' => $endTime?->format('c'), // ISO 8601 with timezone
            'durationMinutes' => $this->getDurationMinutes(),
            'description' => $this->getDescription(),
            'billable' => $this->getBillable(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}

