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

    /**
     * @NoAdminRequired
     */
    public function index(?int $year = null): DataResponse {
        $vacations = $this->vacationMapper->findByUser($this->userId, $year);
        return new DataResponse($vacations);
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): DataResponse {
        try {
            $vacation = $this->vacationMapper->find($id);
            
            // Only allow user to see their own vacations
            if ($vacation->getUserId() !== $this->userId) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            return new DataResponse($vacation);
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
        ?string $notes = null
    ): DataResponse {
        try {
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            
            // Check for overlapping vacations
            if ($this->vacationMapper->hasOverlappingVacation($this->userId, $start, $end)) {
                return new DataResponse([
                    'error' => 'Vacation request overlaps with an existing vacation'
                ], 409);
            }
            
            $vacation = new Vacation();
            $vacation->setUserId($this->userId);
            $vacation->setStartDate($start);
            $vacation->setEndDate($end);
            $vacation->setDays($days);
            $vacation->setStatus('pending');
            $vacation->setNotes($notes);
            $vacation->setCreatedAt(new DateTime());
            $vacation->setUpdatedAt(new DateTime());

            $result = $this->vacationMapper->insert($vacation);
            
            // Notify admins about the new vacation request
            $this->notifyAdminsAboutNewVacationRequest($vacation);
            
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
    public function balance(int $year): DataResponse {
        try {
            // Get employee settings to determine vacation days per year
            $settings = $this->employeeSettingsMapper->findByUserId($this->userId);
            $totalDays = $settings ? $settings->getVacationDaysPerYear() : 20;
            
            // Calculate used days (approved vacations)
            $usedDays = $this->vacationMapper->getTotalDaysUsed($this->userId, $year);
            
            // Calculate pending days
            $pendingVacations = $this->vacationMapper->findByUser($this->userId, $year);
            $pendingDays = 0;
            foreach ($pendingVacations as $vacation) {
                if ($vacation->getStatus() === 'pending') {
                    $pendingDays += $vacation->getDays();
                }
            }
            
            return new DataResponse([
                'year' => $year,
                'totalDays' => $totalDays,
                'usedDays' => $usedDays,
                'pendingDays' => $pendingDays,
                'remainingDays' => $totalDays - $usedDays - $pendingDays,
                'availableDays' => $totalDays - $usedDays,
            ]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
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
            
            $vacations = $this->vacationMapper->findByDateRange($this->userId, $startDate, $endDate);
            
            return new DataResponse($vacations);
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
            return new DataResponse($vacations);
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
            return new DataResponse($result);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }
}

