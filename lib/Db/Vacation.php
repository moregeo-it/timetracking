<?php

declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method DateTime getStartDate()
 * @method void setStartDate(DateTime $startDate)
 * @method DateTime getEndDate()
 * @method void setEndDate(DateTime $endDate)
 * @method int getDays()
 * @method void setDays(int $days)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 */
class Vacation extends Entity implements JsonSerializable {
    protected $userId;
    protected $startDate;
    protected $endDate;
    protected $days;
    protected $status;
    protected $notes;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('userId', 'string');
        $this->addType('startDate', 'datetime');
        $this->addType('endDate', 'datetime');
        $this->addType('days', 'integer');
        $this->addType('status', 'string');
        $this->addType('notes', 'string');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'startDate' => $this->startDate?->format('Y-m-d'),
            'endDate' => $this->endDate?->format('Y-m-d'),
            'days' => $this->days,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}

