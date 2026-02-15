<?php

declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
use OCA\TimeTracking\Db\Vacation;
use OCA\TimeTracking\Db\VacationMapper;
use OCA\TimeTracking\Db\EmployeeSettingsMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\IL10N;
use OCP\IURLGenerator;

class VacationController extends Controller {
    private $userId;
    private $vacationMapper;
    private $employeeSettingsMapper;
    private $groupManager;
    private $userManager;
    private $mailer;
    private $l10n;
    private $urlGenerator;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        VacationMapper $vacationMapper,
        EmployeeSettingsMapper $employeeSettingsMapper,
        IGroupManager $groupManager,
        IUserManager $userManager,
        IMailer $mailer,
        IL10N $l10n,
        IURLGenerator $urlGenerator
    ) {
        parent::__construct($appName, $request);
        $this->userId = $userSession->getUser()->getUID();
        $this->vacationMapper = $vacationMapper;
        $this->employeeSettingsMapper = $employeeSettingsMapper;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->mailer = $mailer;
        $this->l10n = $l10n;
        $this->urlGenerator = $urlGenerator;
    }

    private function isAdmin(): bool {
        return $this->groupManager->isAdmin($this->userId);
    }

    private function notifyAdminsAboutNewVacationRequest(Vacation $vacation): void {
        // Don't send email if the requester is an admin
        if ($this->isAdmin()) {
            return;
        }

        $adminGroup = $this->groupManager->get('admin');
        if ($adminGroup === null) {
            return;
        }

        $admins = $adminGroup->getUsers();
        $requester = $this->userManager->get($this->userId);
        $requesterName = $requester ? $requester->getDisplayName() : $this->userId;
        $requesterEmail = $requester ? $requester->getEMailAddress() : null;

        $startDate = $vacation->getStartDate()->format('d.m.Y');
        $endDate = $vacation->getEndDate()->format('d.m.Y');
        $days = $vacation->getDays();
        
        $appUrl = $this->urlGenerator->linkToRouteAbsolute('timetracking.page.index');

        $subject = $this->l10n->t('Neuer Urlaubsantrag von %s', [$requesterName]);
        
        $body = $this->l10n->t('Hallo,') . "\n\n";
        $body .= $this->l10n->t('%s hat einen neuen Urlaubsantrag eingereicht:', [$requesterName]) . "\n\n";
        $body .= $this->l10n->t('Zeitraum: %s - %s', [$startDate, $endDate]) . "\n";
        $body .= $this->l10n->t('Anzahl Tage: %s', [$days]) . "\n";
        if ($vacation->getNotes()) {
            $body .= $this->l10n->t('Notizen: %s', [$vacation->getNotes()]) . "\n";
        }
        $body .= "\n" . $this->l10n->t('Bitte genehmigen oder lehnen Sie den Antrag ab:') . "\n";
        $body .= $appUrl . "\n\n";
        $body .= $this->l10n->t('Mit freundlichen Grüßen,') . "\n";
        $body .= $this->l10n->t('Ihr Zeiterfassungssystem');

        foreach ($admins as $admin) {
            $adminEmail = $admin->getEMailAddress();
            if ($adminEmail === null || $adminEmail === '') {
                continue;
            }

            try {
                $message = $this->mailer->createMessage();
                $message->setSubject($subject);
                $message->setPlainBody($body);
                $message->setTo([$adminEmail => $admin->getDisplayName()]);
                
                if ($requesterEmail) {
                    $message->setReplyTo([$requesterEmail => $requesterName]);
                }

                $this->mailer->send($message);
            } catch (\Exception $e) {
                // Log error but don't fail the vacation request
                \OC::$server->getLogger()->error(
                    'Failed to send vacation notification email to ' . $adminEmail . ': ' . $e->getMessage(),
                    ['app' => 'timetracking']
                );
            }
        }
    }

    private function notifyEmployeeAboutVacationStatusChange(Vacation $vacation): void {
        $employee = $this->userManager->get($vacation->getUserId());
        if ($employee === null) {
            return;
        }

        $employeeEmail = $employee->getEMailAddress();
        if ($employeeEmail === null || $employeeEmail === '') {
            return;
        }

        $employeeName = $employee->getDisplayName();
        $startDate = $vacation->getStartDate()->format('d.m.Y');
        $endDate = $vacation->getEndDate()->format('d.m.Y');
        $days = $vacation->getDays();
        $status = $vacation->getStatus();
        
        $appUrl = $this->urlGenerator->linkToRouteAbsolute('timetracking.page.index');

        if ($status === 'approved') {
            $subject = $this->l10n->t('Ihr Urlaubsantrag wurde genehmigt');
            $statusText = $this->l10n->t('genehmigt');
        } else {
            $subject = $this->l10n->t('Ihr Urlaubsantrag wurde abgelehnt');
            $statusText = $this->l10n->t('abgelehnt');
        }
        
        $body = $this->l10n->t('Hallo %s,', [$employeeName]) . "\n\n";
        $body .= $this->l10n->t('Ihr Urlaubsantrag wurde %s.', [$statusText]) . "\n\n";
        $body .= $this->l10n->t('Zeitraum: %s - %s', [$startDate, $endDate]) . "\n";
        $body .= $this->l10n->t('Anzahl Tage: %s', [$days]) . "\n";
        $body .= "\n" . $this->l10n->t('Details finden Sie hier:') . "\n";
        $body .= $appUrl . "\n\n";
        $body .= $this->l10n->t('Mit freundlichen Grüßen,') . "\n";
        $body .= $this->l10n->t('Ihr Zeiterfassungssystem');

        try {
            $message = $this->mailer->createMessage();
            $message->setSubject($subject);
            $message->setPlainBody($body);
            $message->setTo([$employeeEmail => $employeeName]);

            $this->mailer->send($message);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error(
                'Failed to send vacation status notification email to ' . $employeeEmail . ': ' . $e->getMessage(),
                ['app' => 'timetracking']
            );
        }
    }

    /**
     * @NoAdminRequired
     */
    public function index(?int $year = null, ?string $userId = null): DataResponse {
        // Admin can filter by userId, otherwise show all for team visibility
        $isAdmin = $this->isAdmin();
        
        if ($userId !== null && $isAdmin) {
            // Admin viewing specific user's vacations
            $vacations = $this->vacationMapper->findByUser($userId, $year);
        } else {
            // Get all vacations for all users (team visibility)
            $vacations = $this->vacationMapper->findAll($year);
        }
        
        // Filter notes for non-owners (except admins)
        $result = [];
        foreach ($vacations as $vacation) {
            $data = $vacation->jsonSerialize();
            // Hide notes if not owner and not admin
            if ($vacation->getUserId() !== $this->userId && !$isAdmin) {
                $data['notes'] = null;
            }
            // Add display name for the user
            $user = $this->userManager->get($vacation->getUserId());
            $data['displayName'] = $user ? $user->getDisplayName() : $vacation->getUserId();
            // Add flags for UI to know what actions are allowed
            $data['canEdit'] = ($vacation->getUserId() === $this->userId || $isAdmin);
            $data['canDelete'] = ($vacation->getUserId() === $this->userId || $isAdmin);
            $result[] = $data;
        }
        
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): DataResponse {
        try {
            $vacation = $this->vacationMapper->find($id);
            $data = $vacation->jsonSerialize();
            
            // Hide notes if not owner and not admin
            if ($vacation->getUserId() !== $this->userId && !$this->isAdmin()) {
                $data['notes'] = null;
            }
            
            // Add display name
            $user = $this->userManager->get($vacation->getUserId());
            $data['displayName'] = $user ? $user->getDisplayName() : $vacation->getUserId();
            
            return new DataResponse($data);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function create(
        string $startDate,
        string $endDate,
        int $days,
        ?string $notes = null,
        ?string $userId = null
    ): DataResponse {
        try {
            // Admins can create vacations for other employees
            $targetUserId = $this->userId;
            if ($userId !== null && $userId !== '' && $userId !== $this->userId) {
                if (!$this->isAdmin()) {
                    return new DataResponse(['error' => 'Only admins can create vacations for other users'], 403);
                }
                $targetUserId = $userId;
            }
            
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            
            // Check for overlapping vacations
            if ($this->vacationMapper->hasOverlappingVacation($targetUserId, $start, $end)) {
                return new DataResponse([
                    'error' => 'Vacation request overlaps with an existing vacation'
                ], 409);
            }
            
            $vacation = new Vacation();
            $vacation->setUserId($targetUserId);
            $vacation->setStartDate($start);
            $vacation->setEndDate($end);
            $vacation->setDays($days);
            // When an admin creates a vacation for another employee, auto-approve it
            $isAdminCreatingForOther = $this->isAdmin() && $targetUserId !== $this->userId;
            $vacation->setStatus($isAdminCreatingForOther ? 'approved' : 'pending');
            $vacation->setNotes($notes);
            $vacation->setCreatedAt(new DateTime());
            $vacation->setUpdatedAt(new DateTime());

            $result = $this->vacationMapper->insert($vacation);
            
            // Only notify admins when an employee submits their own request
            if (!$isAdminCreatingForOther) {
                $this->notifyAdminsAboutNewVacationRequest($vacation);
            }
            
            return new DataResponse($result);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function update(
        int $id,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $days = null,
        ?string $notes = null
    ): DataResponse {
        try {
            $vacation = $this->vacationMapper->find($id);
            
            // Admins can update any vacation, users only their own pending ones
            $isOwner = $vacation->getUserId() === $this->userId;
            $isAdmin = $this->isAdmin();
            
            if (!$isOwner && !$isAdmin) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            // Non-admins can only update pending vacations
            if (!$isAdmin && $vacation->getStatus() !== 'pending') {
                return new DataResponse(['error' => 'Cannot update approved or rejected vacation'], 400);
            }

            // Determine the effective start and end dates for overlap check
            $effectiveStart = $startDate !== null ? new DateTime($startDate) : $vacation->getStartDate();
            $effectiveEnd = $endDate !== null ? new DateTime($endDate) : $vacation->getEndDate();
            
            // Check for overlapping vacations (exclude current vacation)
            if ($this->vacationMapper->hasOverlappingVacation($vacation->getUserId(), $effectiveStart, $effectiveEnd, $id)) {
                return new DataResponse([
                    'error' => 'Vacation request overlaps with an existing vacation'
                ], 409);
            }

            if ($startDate !== null) {
                $vacation->setStartDate($effectiveStart);
            }
            if ($endDate !== null) {
                $vacation->setEndDate($effectiveEnd);
            }
            if ($days !== null) {
                $vacation->setDays($days);
            }
            if ($notes !== null) {
                $vacation->setNotes($notes);
            }
            
            $vacation->setUpdatedAt(new DateTime());
            
            $result = $this->vacationMapper->update($vacation);
            return new DataResponse($result);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(int $id): DataResponse {
        try {
            $vacation = $this->vacationMapper->find($id);
            
            // Admins can delete any vacation, users only their own pending ones
            $isOwner = $vacation->getUserId() === $this->userId;
            $isAdmin = $this->isAdmin();
            
            if (!$isOwner && !$isAdmin) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            // Non-admins can only delete pending vacations
            if (!$isAdmin && $vacation->getStatus() !== 'pending') {
                return new DataResponse(['error' => 'Cannot delete approved or rejected vacation'], 400);
            }

            $this->vacationMapper->delete($vacation);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function balance(int $year, ?string $userId = null): DataResponse {
        try {
            // Admin can view another user's balance
            $targetUserId = $this->userId;
            if ($userId !== null && $this->isAdmin()) {
                $targetUserId = $userId;
            }
            
            // Calculate prorated vacation entitlement based on employment periods within the year.
            // If an employee started mid-year or has varying settings periods with different
            // vacation day entitlements, the total is calculated proportionally per period.
            // This follows German law (§ 5 BUrlG) which requires prorating for partial years.
            $totalDays = $this->calculateProratedVacationDays($targetUserId, $year);
            
            // Calculate used days (approved vacations)
            $usedDays = $this->vacationMapper->getTotalDaysUsed($targetUserId, $year);
            
            // Calculate pending days
            $pendingVacations = $this->vacationMapper->findByUser($targetUserId, $year);
            $pendingDays = 0;
            foreach ($pendingVacations as $vacation) {
                if ($vacation->getStatus() === 'pending') {
                    $pendingDays += $vacation->getDays();
                }
            }
            
            // Calculate carry-over from previous years:
            // Sum of (entitlement - used) for each year from employment start to year-1
            $carryOver = $this->calculateCarryOver($targetUserId, $year);
            
            $totalWithCarryOver = $totalDays + $carryOver;
            
            return new DataResponse([
                'year' => $year,
                'totalDays' => round($totalDays, 2),
                'carryOver' => round($carryOver, 2),
                'totalWithCarryOver' => round($totalWithCarryOver, 2),
                'usedDays' => round($usedDays, 2),
                'pendingDays' => round($pendingDays, 2),
                'remainingDays' => round($totalWithCarryOver - $usedDays - $pendingDays, 2),
                'availableDays' => round($totalWithCarryOver - $usedDays, 2),
            ]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Calculate prorated vacation days for a given year based on employment periods.
     *
     * Considers:
     * - Employment start date (employmentStart or validFrom): if mid-year, entitlement
     *   is prorated from that date to year-end (1/12 per full month, § 5 BUrlG).
     * - Employment end date (validTo): if mid-year, entitlement is prorated to that date.
     * - Multiple settings periods with potentially different vacationDaysPerYear values:
     *   each period contributes proportionally to the months it covers within the year.
     *
     * @param string $userId The user ID
     * @param int $year The year to calculate for
     * @return float Prorated vacation day entitlement, rounded to 1 decimal
     */
    private function calculateProratedVacationDays(string $userId, int $year): float {
        $yearStart = new DateTime("$year-01-01");
        $yearEnd = new DateTime("$year-12-31");
        
        // Get all settings periods that overlap with this year
        $periods = $this->employeeSettingsMapper->findByUserIdInRange($userId, $yearStart, $yearEnd);
        
        if (empty($periods)) {
            // Fallback: use current settings (full year assumed)
            $settings = $this->employeeSettingsMapper->findByUserId($userId);
            return $settings ? (float)$settings->getVacationDaysPerYear() : 20.0;
        }
        
        $totalEntitlement = 0.0;
        
        foreach ($periods as $period) {
            $vacationDaysPerYear = $period->getVacationDaysPerYear();
            if ($vacationDaysPerYear <= 0) {
                continue; // Freelancers etc. with 0 vacation days
            }
            
            // Determine effective start of this period within the year.
            // Use validFrom (the period boundary) rather than employmentStart (global hire date).
            $periodStart = $period->getValidFrom();
            $effectiveStart = ($periodStart !== null && $periodStart > $yearStart) ? $periodStart : $yearStart;
            
            // If the employee's actual employment started after the period start,
            // they're not entitled to vacation before their hire date (§ 5 BUrlG proration).
            // Only apply this when employmentStart falls within the queried year.
            $employmentStart = $period->getEmploymentStart();
            if ($employmentStart !== null && $employmentStart > $effectiveStart && $employmentStart <= $yearEnd) {
                $effectiveStart = $employmentStart;
            }
            
            // Determine effective end of this period within the year
            $periodEnd = $period->getValidTo();
            $effectiveEnd = ($periodEnd !== null && $periodEnd < $yearEnd) ? $periodEnd : $yearEnd;
            
            if ($effectiveStart > $effectiveEnd) {
                continue;
            }
            
            // Calculate the number of full months covered (§ 5 BUrlG: 1/12 per full month)
            $startMonth = (int)$effectiveStart->format('n'); // 1-12
            $startDay = (int)$effectiveStart->format('j');
            $endMonth = (int)$effectiveEnd->format('n');
            $endDay = (int)$effectiveEnd->format('j');
            $endDaysInMonth = (int)$effectiveEnd->format('t');
            
            // Count full months: a month counts if the employee was employed for
            // the entire month or started on the 1st / ended on the last day.
            $fullMonths = 0;
            for ($m = $startMonth; $m <= $endMonth; $m++) {
                $monthStart = ($m === $startMonth) ? $startDay : 1;
                $monthEnd = ($m === $endMonth) ? $endDay : (int)(new DateTime("$year-$m-01"))->format('t');
                $daysInMonth = (int)(new DateTime("$year-$m-01"))->format('t');
                
                // Month counts as full if it starts on day 1 and ends on last day
                if ($monthStart === 1 && $monthEnd === $daysInMonth) {
                    $fullMonths++;
                }
            }
            
            // Prorate: vacationDaysPerYear × (fullMonths / 12)
            $periodEntitlement = $vacationDaysPerYear * ($fullMonths / 12);
            $totalEntitlement += $periodEntitlement;
        }
        
        return round($totalEntitlement, 2);
    }

    /**
     * Calculate vacation carry-over from all previous years.
     *
     * For each year from the employee's employment start year up to (but not including)
     * the given year, the difference (entitlement − used) is accumulated. A positive
     * carry-over means the employee has unused days from the past; a negative value
     * means they took more vacation than entitled (overspent).
     *
     * @param string $userId The user ID
     * @param int $year The target year (carry-over is calculated up to year-1)
     * @return float Cumulative carry-over days (can be negative)
     */
    private function calculateCarryOver(string $userId, int $year): float {
        // Find the earliest employment year from all settings periods
        $allPeriods = $this->employeeSettingsMapper->findAllByUserId($userId);
        if (empty($allPeriods)) {
            return 0.0;
        }

        $earliestYear = $year; // fallback
        foreach ($allPeriods as $period) {
            $start = $period->getEmploymentStart() ?? $period->getValidFrom();
            if ($start !== null) {
                $y = (int)$start->format('Y');
                if ($y < $earliestYear) {
                    $earliestYear = $y;
                }
            }
        }

        // If employment started in the same year or later, no carry-over
        if ($earliestYear >= $year) {
            return 0.0;
        }

        $carryOver = 0.0;
        for ($y = $earliestYear; $y < $year; $y++) {
            $entitlement = $this->calculateProratedVacationDays($userId, $y);
            $used = $this->vacationMapper->getTotalDaysUsed($userId, $y);
            $carryOver += ($entitlement - $used);
        }

        return round($carryOver, 2);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function calendar(int $year, int $month): DataResponse {
        try {
            $startDate = new DateTime("$year-$month-01");
            $endDate = clone $startDate;
            $endDate->modify('last day of this month');
            
            // Get all vacations for all users (team calendar)
            $vacations = $this->vacationMapper->findAllByDateRange($startDate, $endDate);
            
            // Filter notes and add display names
            $isAdmin = $this->isAdmin();
            $result = [];
            foreach ($vacations as $vacation) {
                $data = $vacation->jsonSerialize();
                // Hide notes if not owner and not admin
                if ($vacation->getUserId() !== $this->userId && !$isAdmin) {
                    $data['notes'] = null;
                }
                // Add display name
                $user = $this->userManager->get($vacation->getUserId());
                $data['displayName'] = $user ? $user->getDisplayName() : $vacation->getUserId();
                // Add permission flags
                $data['canEdit'] = ($vacation->getUserId() === $this->userId || $isAdmin);
                $data['canDelete'] = ($vacation->getUserId() === $this->userId || $isAdmin);
                $result[] = $data;
            }
            
            return new DataResponse($result);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get all pending vacation requests (Admin only)
     * @NoAdminRequired
     */
    public function pending(): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }
        
        try {
            $vacations = $this->vacationMapper->findAllPending();
            
            // Add display names
            $result = [];
            foreach ($vacations as $vacation) {
                $data = $vacation->jsonSerialize();
                $user = $this->userManager->get($vacation->getUserId());
                $data['displayName'] = $user ? $user->getDisplayName() : $vacation->getUserId();
                $result[] = $data;
            }
            
            return new DataResponse($result);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Approve a vacation request (Admin only)
     * @NoAdminRequired
     */
    public function approve(int $id): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }
        
        try {
            $vacation = $this->vacationMapper->find($id);
            
            if ($vacation->getStatus() !== 'pending') {
                return new DataResponse(['error' => 'Vacation request is not pending'], 400);
            }
            
            $vacation->setStatus('approved');
            $vacation->setUpdatedAt(new DateTime());
            
            $result = $this->vacationMapper->update($vacation);
            
            $this->notifyEmployeeAboutVacationStatusChange($vacation);
            
            return new DataResponse($result);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Reject a vacation request (Admin only)
     * @NoAdminRequired
     */
    public function reject(int $id): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }
        
        try {
            $vacation = $this->vacationMapper->find($id);
            
            if ($vacation->getStatus() !== 'pending') {
                return new DataResponse(['error' => 'Vacation request is not pending'], 400);
            }
            
            $vacation->setStatus('rejected');
            $vacation->setUpdatedAt(new DateTime());
            
            $result = $this->vacationMapper->update($vacation);
            
            $this->notifyEmployeeAboutVacationStatusChange($vacation);
            
            return new DataResponse($result);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }
}

