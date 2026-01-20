<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
use OCA\TimeTracking\Db\PublicHoliday;
use OCA\TimeTracking\Db\PublicHolidayMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IRequest;
use OCP\IGroupManager;

class PublicHolidayController extends Controller {
    private PublicHolidayMapper $mapper;
    private string $userId;
    private IGroupManager $groupManager;

    public function __construct(
        string $appName,
        IRequest $request,
        PublicHolidayMapper $mapper,
        IGroupManager $groupManager,
        string $userId
    ) {
        parent::__construct($appName, $request);
        $this->mapper = $mapper;
        $this->groupManager = $groupManager;
        $this->userId = $userId;
    }

    private function isAdmin(): bool {
        return $this->groupManager->isAdmin($this->userId);
    }

    /**
     * Get all public holidays
     * 
     * @NoAdminRequired
     */
    public function index(?int $year = null): DataResponse {
        if ($year !== null) {
            $holidays = $this->mapper->findByYear($year);
        } else {
            $holidays = $this->mapper->findAll();
        }
        
        return new DataResponse($holidays);
    }

    /**
     * Get a single public holiday
     * 
     * @NoAdminRequired
     */
    public function show(int $id): DataResponse {
        try {
            $holiday = $this->mapper->find($id);
            return new DataResponse($holiday);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'Holiday not found'], 404);
        }
    }

    /**
     * Create a new public holiday
     * 
     * @NoAdminRequired
     */
    public function create(
        string $date,
        string $name
    ): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can manage public holidays'], 403);
        }

        $holiday = new PublicHoliday();
        $holiday->setDate(new DateTime($date));
        $holiday->setName($name);
        $holiday->setCreatedAt(new DateTime());
        $holiday->setUpdatedAt(new DateTime());

        $result = $this->mapper->insert($holiday);
        return new DataResponse($result, 201);
    }

    /**
     * Update a public holiday
     * 
     * @NoAdminRequired
     */
    public function update(
        int $id,
        string $date,
        string $name
    ): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can manage public holidays'], 403);
        }

        try {
            $holiday = $this->mapper->find($id);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'Holiday not found'], 404);
        }

        $holiday->setDate(new DateTime($date));
        $holiday->setName($name);
        $holiday->setUpdatedAt(new DateTime());

        $result = $this->mapper->update($holiday);
        return new DataResponse($result);
    }

    /**
     * Delete a public holiday
     * 
     * @NoAdminRequired
     */
    public function destroy(int $id): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can manage public holidays'], 403);
        }

        try {
            $holiday = $this->mapper->find($id);
            $this->mapper->delete($holiday);
            return new DataResponse(['status' => 'success']);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'Holiday not found'], 404);
        }
    }

    /**
     * Check if a specific date is a public holiday
     * 
     * @NoAdminRequired
     */
    public function checkDate(string $date): DataResponse {
        $dateObj = new DateTime($date);
        $isHoliday = $this->mapper->isHoliday($dateObj);
        $holidays = $this->mapper->getHolidaysForDate($dateObj);
        
        return new DataResponse([
            'date' => $date,
            'isHoliday' => $isHoliday,
            'holidays' => $holidays,
        ]);
    }

    /**
     * Get holidays for a date range
     * 
     * @NoAdminRequired
     */
    public function range(string $startDate, string $endDate): DataResponse {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        $holidays = $this->mapper->findByDateRange($start, $end);
        $count = $this->mapper->countHolidaysInRange($start, $end);
        
        return new DataResponse([
            'holidays' => $holidays,
            'count' => $count,
        ]);
    }

    /**
     * Import German public holidays for NRW (Nordrhein-Westfalen) for a year
     * 
     * @NoAdminRequired
     */
    public function importGerman(int $year): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can manage public holidays'], 403);
        }

        // Calculate Easter for the year
        $easter = $this->calculateEaster($year);

        // All NRW holidays for the year
        $allHolidays = [
            ['date' => "$year-01-01", 'name' => 'Neujahr'],
            ['date' => (clone $easter)->modify('-2 days')->format('Y-m-d'), 'name' => 'Karfreitag'],
            ['date' => (clone $easter)->modify('+1 day')->format('Y-m-d'), 'name' => 'Ostermontag'],
            ['date' => "$year-05-01", 'name' => 'Tag der Arbeit'],
            ['date' => (clone $easter)->modify('+39 days')->format('Y-m-d'), 'name' => 'Christi Himmelfahrt'],
            ['date' => (clone $easter)->modify('+50 days')->format('Y-m-d'), 'name' => 'Pfingstmontag'],
            ['date' => (clone $easter)->modify('+60 days')->format('Y-m-d'), 'name' => 'Fronleichnam'],
            ['date' => "$year-10-03", 'name' => 'Tag der Deutschen Einheit'],
            ['date' => "$year-11-01", 'name' => 'Allerheiligen'],
            ['date' => "$year-12-25", 'name' => '1. Weihnachtstag'],
            ['date' => "$year-12-26", 'name' => '2. Weihnachtstag'],
        ];

        $imported = 0;
        $skipped = 0;

        foreach ($allHolidays as $holidayData) {
            $date = new DateTime($holidayData['date']);
            
            // Check if holiday already exists for this date
            if ($this->mapper->existsByDate($date)) {
                $skipped++;
                continue;
            }
            
            $holiday = new PublicHoliday();
            $holiday->setDate($date);
            $holiday->setName($holidayData['name']);
            $holiday->setCreatedAt(new DateTime());
            $holiday->setUpdatedAt(new DateTime());
            $this->mapper->insert($holiday);
            $imported++;
        }

        return new DataResponse([
            'status' => 'success',
            'imported' => $imported,
            'skipped' => $skipped,
            'message' => "Imported $imported holidays, skipped $skipped (already exist)",
        ]);
    }

    /**
     * Calculate Easter Sunday for a given year using the Anonymous Gregorian algorithm
     */
    private function calculateEaster(int $year): DateTime {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        
        return new DateTime("$year-$month-$day");
    }
}
