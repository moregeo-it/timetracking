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
 * @method bool getActive()
 * @method void setActive(bool $active)
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
    protected $active;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('customerId', 'integer');
        $this->addType('hourlyRate', 'float');
        $this->addType('budgetHours', 'float');
        $this->addType('active', 'boolean');
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
            'active' => $this->getActive(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}

