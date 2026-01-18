<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCA\TimeTracking\Service\HolidayService;

class HolidayController extends Controller {
    private HolidayService $holidayService;

    public function __construct(
        string $appName,
        IRequest $request,
        HolidayService $holidayService
    ) {
        parent::__construct($appName, $request);
        $this->holidayService = $holidayService;
    }

    /**
     * @NoAdminRequired
     */
    public function index(): JSONResponse {
        $holidays = $this->holidayService->findAll();
        return new JSONResponse($holidays);
    }

    /**
     * @NoAdminRequired
     */
    public function create(string $date, string $name, ?string $region = null): JSONResponse {
        $holiday = $this->holidayService->create($date, $name, $region);
        return new JSONResponse($holiday);
    }

    /**
     * @NoAdminRequired
     */
    public function delete(int $id): JSONResponse {
        $this->holidayService->delete($id);
        return new JSONResponse(['status' => 'ok']);
    }
}
