<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getDate()
 * @method void setDate(string $date)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getRegion()
 * @method void setRegion(?string $region)
 */
class Holiday extends Entity implements JsonSerializable {
    protected $date;
    protected $name;
    protected $region;

    public function __construct() {
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'date' => $this->getDate(),
            'name' => $this->getName(),
            'region' => $this->getRegion(),
        ];
    }
}
