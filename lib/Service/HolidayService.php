<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Service;

use OCA\TimeTracking\Db\Holiday;
use OCA\TimeTracking\Db\HolidayMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class HolidayService {
    private HolidayMapper $mapper;

    public function __construct(HolidayMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function findAll(?string $region = null): array {
        return $this->mapper->findAll($region);
    }

    public function findByYear(int $year, ?string $region = null): array {
        return $this->mapper->findByYear($year, $region);
    }

    public function find(int $id): Holiday {
        return $this->mapper->find($id);
    }

    public function create(string $date, string $name, ?string $region = null): Holiday {
        $holiday = new Holiday();
        $holiday->setDate($date);
        $holiday->setName($name);
        $holiday->setRegion($region);
        return $this->mapper->insert($holiday);
    }

    public function delete(int $id): void {
        try {
            $holiday = $this->mapper->find($id);
            $this->mapper->delete($holiday);
        } catch (DoesNotExistException $e) {
            // Already deleted, ignore
        }
    }
}
