<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int|null getStartTimestamp()
 * @method void setStartTimestamp(?int $startTimestamp)
 * @method int|null getEndTimestamp()
 * @method void setEndTimestamp(?int $endTimestamp)
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
    protected $startTimestamp;
    protected $endTimestamp;
    protected $durationMinutes;
    protected $description;
    protected $billable;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('projectId', 'integer');
        $this->addType('startTimestamp', 'integer');
        $this->addType('endTimestamp', 'integer');
        $this->addType('durationMinutes', 'integer');
        $this->addType('billable', 'boolean');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        // Always return timestamps in ISO 8601 format with UTC timezone
        // This ensures unambiguous communication between server and client
        $utc = new DateTimeZone('UTC');
        
        $startTime = null;
        if ($this->getStartTimestamp()) {
            $startTime = (new DateTimeImmutable('@' . $this->getStartTimestamp()))->setTimezone($utc)->format('c');
        }
        
        $endTime = null;
        if ($this->getEndTimestamp()) {
            $endTime = (new DateTimeImmutable('@' . $this->getEndTimestamp()))->setTimezone($utc)->format('c');
        }
        
        return [
            'id' => $this->getId(),
            'projectId' => $this->getProjectId(),
            'userId' => $this->getUserId(),
            'startTime' => $startTime, // ISO 8601 with UTC timezone (e.g., "2026-01-19T09:45:00+00:00")
            'endTime' => $endTime, // ISO 8601 with UTC timezone
            'durationMinutes' => $this->getDurationMinutes(),
            'description' => $this->getDescription(),
            'billable' => $this->getBillable(),
            'createdAt' => $this->getCreatedAt()?->format('c'),
            'updatedAt' => $this->getUpdatedAt()?->format('c'),
        ];
    }
}

