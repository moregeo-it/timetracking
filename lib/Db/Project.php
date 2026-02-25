<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getCustomerId()
 * @method void setCustomerId(int $customerId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method float|null getHourlyRate()
 * @method void setHourlyRate(?float $hourlyRate)
 * @method float|null getBudgetHours()
 * @method void setBudgetHours(?float $budgetHours)
 * @method string|null getStartDate()
 * @method void setStartDate(?string $startDate)
 * @method string|null getEndDate()
 * @method void setEndDate(?string $endDate)
 * @method bool getActive()
 * @method void setActive(bool $active)
 * @method bool getRequireDescription()
 * @method void setRequireDescription(bool $requireDescription)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class Project extends Entity implements JsonSerializable {
    protected $customerId;
    protected $name;
    protected $description;
    protected $hourlyRate;
    protected $budgetHours;
    protected $startDate;
    protected $endDate;
    protected $active;
    protected $requireDescription;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('customerId', 'integer');
        $this->addType('hourlyRate', 'float');
        $this->addType('budgetHours', 'float');
        $this->addType('active', 'boolean');
        $this->addType('requireDescription', 'boolean');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'customerId' => $this->getCustomerId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'hourlyRate' => $this->getHourlyRate(),
            'budgetHours' => $this->getBudgetHours(),
            'startDate' => $this->getStartDate(),
            'endDate' => $this->getEndDate(),
            'active' => $this->getActive(),
            'requireDescription' => (bool)$this->getRequireDescription(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}

