<?php

namespace App\Livewire\SuperAdmin;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Review;
use App\Models\ServiceCatalog;
use App\Models\ServiceCounter;
use App\Models\SupportTicket;
use App\Models\TechnicianVerification;
use App\Models\User;
use App\Models\WalkInEntry;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

#[Layout('layouts.app')]
#[Title('Super Admin Module')]
class ModulePage extends Component
{
    public string $moduleSlug;

    public bool $showConfirmation = false;

    public string $confirmationTitle = '';

    public string $confirmationDescription = '';

    public string $confirmationActionLabel = 'Confirm';

    public string $confirmationVariant = 'danger';

    public string $confirmationReason = '';

    public bool $confirmationRequiresReason = false;

    public bool $showAssignmentModal = false;

    #[Locked]
    public ?int $assignmentBookingId = null;

    public string $assignmentTechnicianId = '';

    public bool $showSettingEditor = false;

    #[Locked]
    public ?int $editingSettingId = null;

    public string $settingLabel = '';

    public string $settingValue = '';

    public string $settingType = '';

    public bool $showSupportEditor = false;

    #[Locked]
    public ?int $editingSupportTicketId = null;

    public string $supportStatus = 'open';

    public string $supportPriority = 'normal';

    public string $supportMessage = '';

    public bool $showCatalogEditor = false;

    #[Locked]
    public ?int $editingCatalogId = null;

    public string $catalogCode = '';

    public string $catalogName = '';

    public string $catalogCategory = '';

    public string $catalogBasePrice = '';

    public string $catalogDescription = '';

    /** @var array{operation: string, parameters: array<int, mixed>, requiresReason: bool}|null */
    #[Locked]
    public ?array $pendingConfirmation = null;

    /** @var array<string, bool> */
    private array $tableAvailability = [];

    /** @var array<string, bool> */
    private array $columnAvailability = [];

    /**
     * @return array<string, array{label: string, description: string, icon: string}>
     */
    public static function modules(): array
    {
        return [
            'system-health' => [
                'label' => 'System Health',
                'description' => 'Monitor application, database, and queue health.',
                'icon' => 'server-stack',
            ],
            'users-roles' => [
                'label' => 'Users & Roles',
                'description' => 'Manage platform users and access roles.',
                'icon' => 'users',
            ],
            'technician-verification' => [
                'label' => 'Technician Verification',
                'description' => 'Review technician verification submissions.',
                'icon' => 'identification',
            ],
            'service-bookings' => [
                'label' => 'Service Bookings',
                'description' => 'Review and manage customer service bookings.',
                'icon' => 'clipboard-document-list',
            ],
            'dispatch-monitor' => [
                'label' => 'Dispatch Monitor',
                'description' => 'Monitor active dispatch operations.',
                'icon' => 'map',
            ],
            'walk-in-queue' => [
                'label' => 'Walk-in Queue',
                'description' => 'Review and manage walk-in requests.',
                'icon' => 'queue-list',
            ],
            'payments-revenue' => [
                'label' => 'Payments & Revenue',
                'description' => 'Review payments and revenue activity.',
                'icon' => 'banknotes',
            ],
            'ratings-reviews' => [
                'label' => 'Ratings & Reviews',
                'description' => 'Review customer ratings and feedback.',
                'icon' => 'star',
            ],
            'reports-analytics' => [
                'label' => 'Reports & Analytics',
                'description' => 'Review operational reports and analytics.',
                'icon' => 'chart-bar',
            ],
            'support-disputes' => [
                'label' => 'Support & Disputes',
                'description' => 'Review support requests and disputes.',
                'icon' => 'chat-bubble-left-right',
            ],
            'audit-logs' => [
                'label' => 'Audit Logs',
                'description' => 'Review system activity history.',
                'icon' => 'document-text',
            ],
            'service-catalog' => [
                'label' => 'Service Catalog',
                'description' => 'Manage available service offerings.',
                'icon' => 'wrench-screwdriver',
            ],
            'platform-settings' => [
                'label' => 'Platform Settings',
                'description' => 'Control live booking, dispatch, security, and quality policies.',
                'icon' => 'cog-6-tooth',
            ],
        ];
    }

    public function mount(string $module): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless(array_key_exists($module, self::modules()), 404);

        $this->moduleSlug = $module;
    }

    public function render(): View
    {
        $content = $this->contentFor($this->moduleSlug);

        return view('livewire.super-admin.module-page', [
            'module' => self::modules()[$this->moduleSlug],
            'content' => $content,
            'moduleState' => $this->moduleState(),
            'moduleConnections' => $this->moduleConnections(),
        ]);
    }

    /** @return array{label: string, color: string} */
    private function moduleState(): array
    {
        return match ($this->moduleSlug) {
            'system-health' => ['label' => 'Operational', 'color' => 'emerald'],
            'users-roles' => ['label' => 'Access control', 'color' => 'sky'],
            'technician-verification' => ['label' => 'Review queue', 'color' => 'violet'],
            'service-bookings' => ['label' => 'Operations', 'color' => 'blue'],
            'dispatch-monitor' => ['label' => 'Live dispatch', 'color' => 'sky'],
            'walk-in-queue' => ['label' => 'Live queue', 'color' => 'amber'],
            'payments-revenue' => ['label' => 'Finance', 'color' => 'emerald'],
            'ratings-reviews' => ['label' => 'Quality monitor', 'color' => 'violet'],
            'reports-analytics' => ['label' => 'Analytics', 'color' => 'sky'],
            'support-disputes' => ['label' => 'Attention queue', 'color' => 'amber'],
            'audit-logs' => ['label' => 'Governance', 'color' => 'zinc'],
            'service-catalog' => ['label' => 'Catalog control', 'color' => 'teal'],
            'platform-settings' => ['label' => 'Live controls', 'color' => 'zinc'],
        };
    }

    /** @return array<int, array{label: string, icon: string, href: string}> */
    private function moduleConnections(): array
    {
        $link = static fn (string $label, string $icon, string $module): array => [
            'label' => $label,
            'icon' => $icon,
            'href' => route('admin.module', ['module' => $module]),
        ];

        return match ($this->moduleSlug) {
            'system-health' => [$link('Platform settings', 'cog-6-tooth', 'platform-settings'), $link('Audit logs', 'document-text', 'audit-logs')],
            'users-roles' => [$link('Technician verification', 'identification', 'technician-verification'), $link('Audit logs', 'document-text', 'audit-logs')],
            'technician-verification' => [$link('Users & roles', 'users', 'users-roles'), $link('Dispatch monitor', 'map', 'dispatch-monitor')],
            'service-bookings' => [$link('Dispatch monitor', 'map', 'dispatch-monitor'), $link('Payments & revenue', 'banknotes', 'payments-revenue'), $link('Reports & analytics', 'chart-bar', 'reports-analytics')],
            'dispatch-monitor' => [$link('Service bookings', 'clipboard-document-list', 'service-bookings'), $link('Technician verification', 'identification', 'technician-verification')],
            'walk-in-queue' => [$link('Service bookings', 'clipboard-document-list', 'service-bookings'), $link('Reports & analytics', 'chart-bar', 'reports-analytics')],
            'payments-revenue' => [$link('Service bookings', 'clipboard-document-list', 'service-bookings'), $link('Reports & analytics', 'chart-bar', 'reports-analytics')],
            'ratings-reviews' => [$link('Service bookings', 'clipboard-document-list', 'service-bookings'), $link('Reports & analytics', 'chart-bar', 'reports-analytics')],
            'reports-analytics' => [$link('Service bookings', 'clipboard-document-list', 'service-bookings'), $link('Payments & revenue', 'banknotes', 'payments-revenue')],
            'support-disputes' => [$link('Users & roles', 'users', 'users-roles'), $link('Service bookings', 'clipboard-document-list', 'service-bookings'), $link('Audit logs', 'document-text', 'audit-logs')],
            'audit-logs' => [$link('Users & roles', 'users', 'users-roles'), $link('Service bookings', 'clipboard-document-list', 'service-bookings')],
            'service-catalog' => [$link('Platform settings', 'cog-6-tooth', 'platform-settings'), $link('Service bookings', 'clipboard-document-list', 'service-bookings')],
            'platform-settings' => [$link('System health', 'server-stack', 'system-health'), $link('Service bookings', 'clipboard-document-list', 'service-bookings')],
            default => [],
        };
    }

    public function requestConfirmation(string $action, int $id = 0): void
    {
        $this->authorizeSuperAdmin();

        $confirmation = $this->confirmationDefinition($action, $id);

        $this->pendingConfirmation = [
            'operation' => $confirmation['operation'],
            'parameters' => $confirmation['parameters'],
            'requiresReason' => $confirmation['requiresReason'] ?? false,
        ];
        $this->confirmationTitle = $confirmation['title'];
        $this->confirmationDescription = $confirmation['description'];
        $this->confirmationActionLabel = $confirmation['label'];
        $this->confirmationVariant = $confirmation['variant'];
        $this->confirmationReason = '';
        $this->confirmationRequiresReason = (bool) ($confirmation['requiresReason'] ?? false);
        $this->showConfirmation = true;
    }

    public function executeConfirmedAction(): void
    {
        $this->authorizeSuperAdmin();

        $pending = $this->pendingConfirmation;

        if ($pending === null) {
            return;
        }

        if ($pending['requiresReason']) {
            Validator::make(['reason' => $this->confirmationReason], [
                'reason' => ['required', 'string', 'max:1000'],
            ])->validate();
        }

        $confirmationReason = trim($this->confirmationReason);

        $this->cancelConfirmation();

        try {
            $this->dispatchConfirmedAction($pending, $confirmationReason);
        } catch (Throwable $exception) {
            $this->notifyActionFailure($exception);
        }
    }

    public function cancelConfirmation(): void
    {
        $this->showConfirmation = false;
        $this->pendingConfirmation = null;
        $this->confirmationTitle = '';
        $this->confirmationDescription = '';
        $this->confirmationActionLabel = 'Confirm';
        $this->confirmationVariant = 'danger';
        $this->confirmationReason = '';
        $this->confirmationRequiresReason = false;
    }

    /**
     * @return array{title: string, description: string, label: string, variant: string, operation: string, parameters: array<int, mixed>, requiresReason?: bool}
     */
    private function confirmationDefinition(string $action, int $id): array
    {
        $targetId = $id > 0 ? $id : null;
        $targetRequired = fn (): int => abort(422, 'A confirmation target is required.');

        return match ($action) {
            'run-scheduler' => [
                'title' => 'Run scheduler now?',
                'description' => 'Scheduled tasks will be processed immediately.',
                'label' => 'Run scheduler',
                'variant' => 'primary',
                'operation' => 'runSystemAction',
                'parameters' => ['run-scheduler'],
            ],
            'clear-cache' => [
                'title' => 'Clear application cache?',
                'description' => 'Cached application data will be removed and rebuilt on the next request.',
                'label' => 'Clear cache',
                'variant' => 'danger',
                'operation' => 'runSystemAction',
                'parameters' => ['clear-cache'],
            ],
            'retry-failed-jobs' => [
                'title' => 'Retry failed jobs?',
                'description' => 'All failed jobs will be queued for another attempt.',
                'label' => 'Retry jobs',
                'variant' => 'primary',
                'operation' => 'runSystemAction',
                'parameters' => ['retry-failed-jobs'],
            ],
            'make-admin' => [
                'title' => 'Make this user an Admin?',
                'description' => 'This changes the user\'s access role and permissions.',
                'label' => 'Make admin',
                'variant' => 'primary',
                'operation' => 'updateUserRole',
                'parameters' => [$targetId ?? $targetRequired(), 'admin'],
            ],
            'make-technician' => [
                'title' => 'Make this user a Technician?',
                'description' => 'This changes the user\'s access role and permissions.',
                'label' => 'Make technician',
                'variant' => 'primary',
                'operation' => 'updateUserRole',
                'parameters' => [$targetId ?? $targetRequired(), 'technician'],
            ],
            'make-dispatcher' => [
                'title' => 'Make this user a Dispatcher?',
                'description' => 'This changes the user\'s access role and dispatch permissions.',
                'label' => 'Make dispatcher',
                'variant' => 'primary',
                'operation' => 'updateUserRole',
                'parameters' => [$targetId ?? $targetRequired(), 'dispatcher'],
            ],
            'make-support' => [
                'title' => 'Make this user Support staff?',
                'description' => 'This changes the user\'s access role and support permissions.',
                'label' => 'Make support staff',
                'variant' => 'primary',
                'operation' => 'updateUserRole',
                'parameters' => [$targetId ?? $targetRequired(), 'support'],
            ],
            'make-finance' => [
                'title' => 'Make this user Finance staff?',
                'description' => 'This changes the user\'s access role and finance permissions.',
                'label' => 'Make finance staff',
                'variant' => 'primary',
                'operation' => 'updateUserRole',
                'parameters' => [$targetId ?? $targetRequired(), 'finance'],
            ],
            'make-customer' => [
                'title' => 'Make this user a Customer?',
                'description' => 'This removes staff access and returns the account to the customer workspace.',
                'label' => 'Make customer',
                'variant' => 'primary',
                'operation' => 'updateUserRole',
                'parameters' => [$targetId ?? $targetRequired(), 'customer'],
            ],
            'suspend-user' => [
                'title' => 'Suspend this account?',
                'description' => 'The user will no longer be able to access the platform.',
                'label' => 'Suspend account',
                'variant' => 'danger',
                'operation' => 'updateUserStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'suspended'],
            ],
            'revoke-user-sessions' => [
                'title' => 'Revoke all sessions?',
                'description' => 'This will sign the user out of all active sessions.',
                'label' => 'Revoke sessions',
                'variant' => 'danger',
                'operation' => 'revokeUserSessions',
                'parameters' => [$targetId ?? $targetRequired()],
            ],
            'approve-verification' => [
                'title' => 'Approve this technician verification?',
                'description' => 'The submitted technician profile will be marked as approved.',
                'label' => 'Approve verification',
                'variant' => 'primary',
                'operation' => 'updateVerificationStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'approved'],
            ],
            'request-verification-information' => [
                'title' => 'Request more information?',
                'description' => 'The technician will need to provide more information before this submission can be approved.',
                'label' => 'Request information',
                'variant' => 'primary',
                'operation' => 'updateVerificationStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'information_requested'],
                'requiresReason' => true,
            ],
            'reject-verification' => [
                'title' => 'Reject this technician verification?',
                'description' => 'The submission will be marked rejected and the decision reason will be recorded.',
                'label' => 'Reject verification',
                'variant' => 'danger',
                'operation' => 'updateVerificationStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'rejected'],
                'requiresReason' => true,
            ],
            'suspend-verification' => [
                'title' => 'Suspend this technician verification?',
                'description' => 'The technician account will be suspended and the decision reason will be recorded.',
                'label' => 'Suspend verification',
                'variant' => 'danger',
                'operation' => 'updateVerificationStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'suspended'],
                'requiresReason' => true,
            ],
            'initialize-settings' => [
                'title' => 'Initialize platform controls?',
                'description' => 'Default booking, dispatch, security, finance, notification, and quality controls will be created without overwriting existing values.',
                'label' => 'Initialize controls',
                'variant' => 'primary',
                'operation' => 'initializePlatformSettings',
                'parameters' => [],
            ],
            'start-booking' => [
                'title' => 'Start this booking?',
                'description' => 'The booking will move into in-progress work.',
                'label' => 'Start job',
                'variant' => 'primary',
                'operation' => 'updateBookingStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'in_progress'],
            ],
            'complete-booking' => [
                'title' => 'Mark this booking completed?',
                'description' => 'The booking will be removed from active work and marked completed.',
                'label' => 'Complete job',
                'variant' => 'primary',
                'operation' => 'updateBookingStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'completed'],
            ],
            'cancel-booking' => [
                'title' => 'Cancel this booking?',
                'description' => 'The booking will be marked cancelled and removed from active work.',
                'label' => 'Cancel booking',
                'variant' => 'danger',
                'operation' => 'updateBookingStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'cancelled', 'Cancelled by Super Admin'],
            ],
            'mark-payment-paid' => [
                'title' => 'Mark this payment as paid?',
                'description' => 'The payment record will be marked as paid.',
                'label' => 'Mark paid',
                'variant' => 'primary',
                'operation' => 'updatePaymentStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'paid'],
            ],
            'refund-payment' => [
                'title' => 'Refund this payment?',
                'description' => 'The payment record will be marked as refunded.',
                'label' => 'Refund payment',
                'variant' => 'danger',
                'operation' => 'updatePaymentStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'refunded'],
            ],
            'hide-review' => [
                'title' => 'Hide this review?',
                'description' => 'The review will no longer be visible in the published feedback list.',
                'label' => 'Hide review',
                'variant' => 'danger',
                'operation' => 'updateReviewStatus',
                'parameters' => [$targetId ?? $targetRequired(), 'hidden'],
            ],
            'deactivate-service' => [
                'title' => 'Deactivate this service?',
                'description' => 'This service will no longer be available for new bookings.',
                'label' => 'Deactivate service',
                'variant' => 'danger',
                'operation' => 'updateCatalogStatus',
                'parameters' => [$targetId ?? $targetRequired(), false],
            ],
            default => abort(422, 'The requested confirmation is not available.'),
        };
    }

    /** @param array{operation: string, parameters: array<int, mixed>, requiresReason: bool} $pending */
    private function dispatchConfirmedAction(array $pending, string $confirmationReason = ''): void
    {
        $parameters = $pending['parameters'];

        match ($pending['operation']) {
            'runSystemAction' => $this->runSystemAction((string) $parameters[0]),
            'updateUserRole' => $this->updateUserRole((int) $parameters[0], (string) $parameters[1]),
            'updateUserStatus' => $this->updateUserStatus((int) $parameters[0], (string) $parameters[1]),
            'revokeUserSessions' => $this->revokeUserSessions((int) $parameters[0]),
            'updateVerificationStatus' => $this->updateVerificationStatus((int) $parameters[0], (string) $parameters[1], $confirmationReason),
            'updateBookingStatus' => $this->updateBookingStatus(
                (int) $parameters[0],
                (string) $parameters[1],
                (string) ($parameters[2] ?? ''),
            ),
            'updatePaymentStatus' => $this->updatePaymentStatus((int) $parameters[0], (string) $parameters[1]),
            'updateReviewStatus' => $this->updateReviewStatus((int) $parameters[0], (string) $parameters[1]),
            'updateCatalogStatus' => $this->updateCatalogStatus((int) $parameters[0], (bool) $parameters[1]),
            'initializePlatformSettings' => $this->initializePlatformSettings(),
            default => abort(422, 'The requested confirmation is not available.'),
        };
    }

    private function notifyActionFailure(Throwable $exception): void
    {
        $message = $exception instanceof HttpExceptionInterface ? trim($exception->getMessage()) : '';

        if (! $exception instanceof HttpExceptionInterface) {
            report($exception);
        }

        Flux::toast(
            variant: 'danger',
            text: $message !== '' ? $message : 'Action failed. Please try again.',
        );
    }

    public function updateUserRole(int $userId, string $role): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($role, ['superadmin', 'admin', 'dispatcher', 'support', 'finance', 'technician', 'customer']);
        $user = User::query()->findOrFail($userId);

        abort_if(
            $user->role === 'superadmin'
            && $role !== 'superadmin'
            && User::query()->where('role', 'superadmin')->count() === 1,
            422,
            'The final Super Admin cannot be demoted.',
        );

        $user->update(['role' => $role]);
        $this->recordAudit('user.updated', 'user', $userId, ['role' => $role]);
        $this->flashStatus('User role updated.');
    }

    public function updateUserStatus(int $userId, string $status): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($status, ['active', 'suspended']);
        abort_unless($this->columnExists('users', 'account_status'), 422, 'User account status is not available yet.');
        abort_if(auth()->id() === $userId && $status === 'suspended', 422, 'You cannot suspend your own account.');

        $user = User::query()->findOrFail($userId);
        $user->update(['account_status' => $status]);
        $this->recordAudit('user.updated', 'user', $userId, ['account_status' => $status]);
        $this->flashStatus('User account status updated.');
    }

    public function revokeUserSessions(int $userId): void
    {
        $this->authorizeSuperAdmin();
        User::query()->findOrFail($userId);
        $count = Schema::hasTable('sessions') ? DB::table('sessions')->where('user_id', $userId)->delete() : 0;
        $this->recordAudit('user.sessions_revoked', 'user', $userId, ['sessions' => $count]);
        $this->flashStatus("{$count} user session(s) revoked.");
    }

    public function updateVerificationStatus(int $verificationId, string $status, string $reason = ''): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($status, ['under_review', 'information_requested', 'approved', 'rejected', 'suspended']);
        abort_unless($this->tableExists('technician_verifications'), 422, 'Technician verification is not available yet.');
        abort_if(in_array($status, ['information_requested', 'rejected', 'suspended'], true) && trim($reason) === '', 422, 'A decision reason is required.');

        $verification = TechnicianVerification::query()->findOrFail($verificationId);
        $data = [
            'status' => $status,
            'reviewer_id' => auth()->id(),
            'reviewed_at' => now(),
            'decision_reason' => $reason !== '' ? $reason : null,
            'updated_at' => now(),
        ];
        $verification->update($data);

        if ($this->columnExists('users', 'account_status') && in_array($status, ['approved', 'suspended'], true)) {
            User::query()->findOrFail($verification->user_id)->update([
                'account_status' => $status === 'approved' ? 'active' : 'suspended',
            ]);
        }

        $this->recordAudit('technician.verification_updated', 'technician_verification', $verificationId, $data);
        $this->flashStatus('Technician verification decision saved.');
    }

    public function updateBookingStatus(int $bookingId, string $status, string $reason = ''): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($status, ['pending', 'matching', 'assigned', 'en_route', 'in_progress', 'completed', 'cancelled']);
        abort_unless($this->tableExists('bookings'), 422, 'Service bookings are not available yet.');
        abort_if($status === 'cancelled' && trim($reason) === '', 422, 'A cancellation reason is required.');

        $data = ['status' => $status, 'updated_at' => now()];
        $paymentId = null;
        $paymentCreated = false;

        DB::transaction(function () use ($bookingId, $status, $reason, &$data, &$paymentId, &$paymentCreated): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if ($this->columnExists('bookings', 'cancellation_reason')) {
                $data['cancellation_reason'] = $status === 'cancelled' ? $reason : null;
            }
            if ($this->columnExists('bookings', 'assigned_technician_id') && in_array($status, ['pending', 'matching'], true)) {
                $data['assigned_technician_id'] = null;
            }

            $booking->update($data);
            if ($booking->assigned_technician_id && in_array($status, ['pending', 'matching', 'completed', 'cancelled'], true)) {
                $this->releaseTechnicianIfIdle((int) $booking->assigned_technician_id);
            }

            if ($status === 'completed' && $this->tableExists('payments')) {
                $payment = Booking::query()->findOrFail($bookingId)->ensurePayment();
                $paymentId = (int) $payment->id;
                $paymentCreated = $payment->wasRecentlyCreated;
            }
        });

        $this->recordAudit('booking.updated', 'booking', $bookingId, $data);
        if ($paymentCreated) {
            $this->recordAudit('payment.created', 'payment', $paymentId, ['booking_id' => $bookingId, 'status' => 'pending']);
        }
        $this->flashStatus($paymentCreated ? 'Booking completed and payment record created.' : 'Booking status updated.');
    }

    public function assignBooking(int $bookingId, ?int $technicianId): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->tableExists('bookings'), 422, 'Service bookings are not available yet.');
        abort_unless($this->columnExists('bookings', 'assigned_technician_id'), 422, 'Booking assignment is not available yet.');
        $booking = Booking::query()->findOrFail($bookingId);

        if ($technicianId !== null) {
            $technician = User::query()
                ->where('id', $technicianId)
                ->where('role', 'technician')
                ->when($this->columnExists('users', 'account_status'), fn ($query) => $query->where('account_status', 'active'))
                ->first();
            abort_unless($technician, 422, 'Technician is unavailable.');
            abort_unless(
                $this->tableExists('technician_verifications')
                && TechnicianVerification::query()->where('user_id', $technicianId)->where('status', 'approved')->exists(),
                422,
                'Technician is not verified.',
            );
            $maxActiveJobs = max(1, (int) ($this->tableExists('platform_settings') ? PlatformSetting::query()->where('key', 'max_active_jobs')->value('value') : 1));
            abort_if(
                Booking::query()
                    ->where('assigned_technician_id', $technicianId)
                    ->where('id', '!=', $bookingId)
                    ->whereIn('status', ['assigned', 'en_route', 'in_progress'])
                    ->count() >= $maxActiveJobs,
                422,
                'Technician has reached the active assignment limit.',
            );
        }

        $booking->update([
            'assigned_technician_id' => $technicianId,
            'status' => $technicianId ? 'assigned' : 'matching',
        ]);
        if ($booking->assigned_technician_id && (int) $booking->assigned_technician_id !== $technicianId) {
            $this->releaseTechnicianIfIdle((int) $booking->assigned_technician_id);
        }
        if ($technicianId !== null && $this->columnExists('users', 'availability_status')) {
            $technician->update(['availability_status' => 'busy']);
        }
        $this->recordAudit('booking.updated', 'booking', $bookingId, ['assigned_technician_id' => $technicianId]);
        $this->flashStatus($technicianId ? 'Technician assigned to booking.' : 'Booking returned to matching queue.');
    }

    public function openAssignment(int $bookingId): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->moduleSlug === 'dispatch-monitor', 404);
        abort_unless($this->tableExists('bookings'), 422, 'Service bookings are not available yet.');

        $booking = Booking::query()->findOrFail($bookingId);
        abort_unless(in_array($booking->status, ['pending', 'matching', 'assigned'], true), 422, 'Only active dispatch requests can be assigned.');

        $this->assignmentBookingId = (int) $booking->id;
        $this->assignmentTechnicianId = $booking->assigned_technician_id ? (string) $booking->assigned_technician_id : '';
        $this->showAssignmentModal = true;
    }

    public function saveAssignment(): void
    {
        $this->authorizeSuperAdmin();

        $validated = Validator::make([
            'booking_id' => $this->assignmentBookingId,
            'technician_id' => $this->assignmentTechnicianId !== '' ? $this->assignmentTechnicianId : null,
        ], [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'technician_id' => ['required', 'integer', 'exists:users,id'],
        ])->validate();

        $this->assignBooking((int) $validated['booking_id'], (int) $validated['technician_id']);
        $this->cancelAssignment();
    }

    public function cancelAssignment(): void
    {
        $this->showAssignmentModal = false;
        $this->assignmentBookingId = null;
        $this->assignmentTechnicianId = '';
    }

    public function updateTechnicianAvailability(int $technicianId, string $status): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($status, ['available', 'busy', 'offline']);
        abort_unless($this->columnExists('users', 'availability_status'), 422, 'Technician availability is not available yet.');
        $technician = User::query()->where('id', $technicianId)->where('role', 'technician')->first();
        abort_unless($technician, 404);
        abort_if(
            $status === 'available'
            && $this->tableExists('bookings')
            && Booking::query()->where('assigned_technician_id', $technicianId)->whereIn('status', ['assigned', 'en_route', 'in_progress'])->exists(),
            422,
            'Technician has an active assignment.',
        );

        $technician->update(['availability_status' => $status]);
        $this->recordAudit('technician.availability_updated', 'user', $technicianId, ['availability_status' => $status]);
        $this->flashStatus('Technician availability updated.');
    }

    private function releaseTechnicianIfIdle(int $technicianId): void
    {
        if (! $this->columnExists('users', 'availability_status') || ! $this->tableExists('bookings')) {
            return;
        }

        $hasActiveAssignment = Booking::query()
            ->where('assigned_technician_id', $technicianId)
            ->whereIn('status', ['assigned', 'en_route', 'in_progress'])
            ->exists();

        if (! $hasActiveAssignment) {
            User::query()->whereKey($technicianId)->update([
                'availability_status' => 'available',
            ]);
        }
    }

    public function updateWalkInStatus(int $entryId, string $status): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($status, ['waiting', 'called', 'serving', 'on_hold', 'completed', 'no_show', 'cancelled']);
        abort_unless($this->tableExists('walk_in_entries'), 422, 'Walk-in queue is not available yet.');
        $entry = WalkInEntry::query()->findOrFail($entryId);

        $data = ['status' => $status, 'updated_at' => now()];
        if ($status === 'called') {
            $data['called_at'] = now();
        }
        if ($status === 'serving') {
            $data['service_started_at'] = now();
        }
        if ($status === 'completed') {
            $data['completed_at'] = now();
        }
        $entry->update($data);
        if ($entry->counter_id && $this->tableExists('service_counters')) {
            ServiceCounter::query()->whereKey($entry->counter_id)->update([
                'status' => in_array($status, ['called', 'serving'], true) ? 'busy' : 'available',
            ]);
        }
        $this->recordAudit('walk_in.updated', 'walk_in_entry', $entryId, $data);
        $this->flashStatus('Walk-in queue status updated.');
    }

    public function updateWalkInPriority(int $entryId, string $priority): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($priority, ['standard', 'priority']);
        abort_unless($this->tableExists('walk_in_entries'), 422, 'Walk-in queue is not available yet.');
        $entry = WalkInEntry::query()->findOrFail($entryId);
        $entry->update(['priority' => $priority]);
        $this->recordAudit('walk_in.updated', 'walk_in_entry', $entryId, ['priority' => $priority]);
        $this->flashStatus('Walk-in priority updated.');
    }

    public function updatePaymentStatus(int $paymentId, string $status): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($status, ['pending', 'paid', 'failed', 'refunded']);
        abort_unless($this->tableExists('payments'), 422, 'Payments are not available yet.');
        $payment = Payment::query()->with('booking:id,status')->findOrFail($paymentId);
        abort_if($status === 'paid' && $payment->booking?->status !== 'completed', 422, 'Payments can only be marked paid after booking completion.');
        $data = ['status' => $status];
        if ($status === 'paid') {
            $data['paid_at'] = now();
        }
        $payment->update($data);
        $this->recordAudit($status === 'paid' ? 'payment.paid' : 'payments.updated', 'payment', $paymentId, $data);
        $this->flashStatus('Payment status updated.');
    }

    public function updateReviewStatus(int $reviewId, string $status): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($status, ['published', 'flagged', 'hidden']);
        abort_unless($this->tableExists('reviews'), 422, 'Ratings and reviews are not available yet.');
        $review = Review::query()->findOrFail($reviewId);
        $review->update(['status' => $status]);
        $this->recordAudit('reviews.updated', 'review', $reviewId, ['status' => $status]);
        $this->flashStatus('Review moderation status updated.');
    }

    public function updateSupportStatus(int $ticketId, string $status): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($status, ['open', 'in_progress', 'resolved', 'closed']);
        abort_unless($this->tableExists('support_tickets'), 422, 'Support tickets are not available yet.');
        $ticket = SupportTicket::query()->findOrFail($ticketId);
        $ticket->update(['status' => $status]);
        $this->recordAudit('support.updated', 'support', $ticketId, ['status' => $status]);
        $this->flashStatus('Support ticket status updated.');
    }

    public function assignSupportTicket(int $ticketId): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->tableExists('support_tickets'), 422, 'Support tickets are not available yet.');
        $ticket = SupportTicket::query()->findOrFail($ticketId);
        $ticket->update(['assigned_to' => auth()->id()]);
        $this->recordAudit('support.updated', 'support', $ticketId, ['assigned_to' => auth()->id()]);
        $this->flashStatus('Support ticket assigned to you.');
    }

    public function openSupportEditor(int $ticketId): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->tableExists('support_tickets'), 422, 'Support tickets are not available yet.');

        $ticket = SupportTicket::query()->findOrFail($ticketId);

        $this->editingSupportTicketId = (int) $ticket->id;
        $this->supportStatus = (string) $ticket->status;
        $this->supportPriority = (string) $ticket->priority;
        $this->supportMessage = (string) ($ticket->latest_message ?? '');
        $this->showSupportEditor = true;
    }

    public function saveSupportTicket(): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->editingSupportTicketId, 422, 'A support ticket must be selected.');

        $validated = Validator::make([
            'status' => $this->supportStatus,
            'priority' => $this->supportPriority,
            'latest_message' => $this->supportMessage,
        ], [
            'status' => ['required', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'latest_message' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $ticket = SupportTicket::query()->findOrFail($this->editingSupportTicketId);
        $data = [
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'latest_message' => $validated['latest_message'] !== '' ? $validated['latest_message'] : null,
        ];
        if ($data['latest_message'] !== null) {
            $data['last_response_at'] = now();
        }
        $ticket->update($data);
        $this->recordAudit('support.updated', 'support', $this->editingSupportTicketId, $data);
        $this->cancelSupportEditor();
        $this->flashStatus('Support ticket updated.');
    }

    public function cancelSupportEditor(): void
    {
        $this->showSupportEditor = false;
        $this->editingSupportTicketId = null;
        $this->supportStatus = 'open';
        $this->supportPriority = 'normal';
        $this->supportMessage = '';
    }

    public function openCatalogEditor(int $serviceId = 0): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->tableExists('service_catalog'), 422, 'Service catalog is not available yet.');

        $this->editingCatalogId = null;
        $this->catalogCode = '';
        $this->catalogName = '';
        $this->catalogCategory = '';
        $this->catalogBasePrice = '';
        $this->catalogDescription = '';

        if ($serviceId > 0) {
            $service = ServiceCatalog::query()->findOrFail($serviceId);
            $this->editingCatalogId = (int) $service->id;
            $this->catalogCode = (string) $service->code;
            $this->catalogName = (string) $service->name;
            $this->catalogCategory = (string) $service->category;
            $this->catalogBasePrice = $service->base_price !== null ? (string) $service->base_price : '';
            $this->catalogDescription = (string) ($service->description ?? '');
        }

        $this->showCatalogEditor = true;
    }

    public function saveCatalogService(): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->tableExists('service_catalog'), 422, 'Service catalog is not available yet.');

        $validated = Validator::make([
            'code' => trim($this->catalogCode),
            'name' => trim($this->catalogName),
            'category' => trim($this->catalogCategory),
            'base_price' => $this->catalogBasePrice !== '' ? $this->catalogBasePrice : null,
            'description' => trim($this->catalogDescription) !== '' ? trim($this->catalogDescription) : null,
        ], [
            'code' => ['required', 'string', 'max:40', Rule::unique('service_catalog', 'code')->ignore($this->editingCatalogId)],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $data = [
            ...$validated,
        ];
        if ($this->editingCatalogId) {
            $service = ServiceCatalog::query()->findOrFail($this->editingCatalogId);
            $service->update($data);
            $serviceId = $this->editingCatalogId;
            $message = 'Service catalog entry updated.';
            $action = 'catalog.updated';
        } else {
            $data['is_active'] = true;
            $serviceId = (int) ServiceCatalog::query()->create($data)->id;
            $message = 'Service catalog entry created.';
            $action = 'catalog.created';
        }
        $this->recordAudit($action, 'catalog', $serviceId, $validated);
        $this->cancelCatalogEditor();
        $this->flashStatus($message);
    }

    public function cancelCatalogEditor(): void
    {
        $this->showCatalogEditor = false;
        $this->editingCatalogId = null;
        $this->catalogCode = '';
        $this->catalogName = '';
        $this->catalogCategory = '';
        $this->catalogBasePrice = '';
        $this->catalogDescription = '';
    }

    public function updateCatalogStatus(int $serviceId, bool $active): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->tableExists('service_catalog'), 422, 'Service catalog is not available yet.');
        $service = ServiceCatalog::query()->findOrFail($serviceId);
        $service->update(['is_active' => $active]);
        $this->recordAudit('catalog.updated', 'catalog', $serviceId, ['is_active' => $active]);
        $this->flashStatus($active ? 'Service activated.' : 'Service deactivated.');
    }

    public function runSystemAction(string $action): void
    {
        $this->authorizeSuperAdmin();
        $this->validateValue($action, ['clear-cache', 'retry-failed-jobs', 'run-scheduler']);

        match ($action) {
            'clear-cache' => Cache::flush(),
            'retry-failed-jobs' => Artisan::call('queue:retry', ['id' => ['all']]),
            'run-scheduler' => Artisan::call('schedule:run'),
        };
        $this->recordAudit("system.{$action}", 'system');
        $this->flashStatus('System action completed.');
    }

    public function exportAnalytics(): StreamedResponse
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->moduleSlug === 'reports-analytics', 404);

        $content = $this->analyticsContent();

        return response()->streamDownload(function () use ($content): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['FixTrack Reports & Analytics']);
            fputcsv($handle, []);
            fputcsv($handle, ['Metric', 'Value']);

            foreach ($content['stats'] as $stat) {
                fputcsv($handle, [$stat['label'], $stat['value']]);
            }

            foreach ($content['sections'] as $section) {
                fputcsv($handle, []);
                fputcsv($handle, [$section['title']]);
                fputcsv($handle, array_column($section['columns'], 'label'));

                foreach ($section['rows'] as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        }, 'fixtrack-analytics.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    /** @param array<int, string> $allowed */
    private function validateValue(string $value, array $allowed): string
    {
        return Validator::make(['value' => $value], ['value' => ['required', Rule::in($allowed)]])->validate()['value'];
    }

    /** @param array<string, mixed> $details */
    private function recordAudit(string $action, string $targetType, ?int $targetId = null, array $details = []): void
    {
        if (! $this->tableExists('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private function flashStatus(string $message): void
    {
        Flux::toast(variant: 'success', text: $message);
    }

    /**
     * @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>}
     */
    private function contentFor(string $module): array
    {
        return match ($module) {
            'system-health' => $this->systemHealthContent(),
            'users-roles' => $this->usersContent(),
            'technician-verification' => $this->verificationContent(),
            'service-bookings' => $this->bookingsContent(),
            'dispatch-monitor' => $this->dispatchContent(),
            'walk-in-queue' => $this->walkInContent(),
            'payments-revenue' => $this->paymentsContent(),
            'ratings-reviews' => $this->ratingsContent(),
            'reports-analytics' => $this->analyticsContent(),
            'support-disputes' => $this->supportContent(),
            'audit-logs' => $this->auditContent(),
            'service-catalog' => $this->catalogContent(),
            'platform-settings' => $this->settingsContent(),
        };
    }

    public function initializePlatformSettings(): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->tableExists('platform_settings'), 422, 'Platform settings are not available yet.');

        $defaults = collect([
            ['operations', 'booking_auto_match', 'Automatic technician matching', 'true', 'boolean'],
            ['operations', 'maintenance_mode', 'Maintenance mode', 'false', 'boolean'],
            ['operations', 'dispatch_radius_km', 'Dispatch radius (km)', '15', 'number'],
            ['operations', 'max_active_jobs', 'Maximum active jobs per technician', '3', 'number'],
            ['booking', 'quick_booking_enabled', 'Quick booking', 'true', 'boolean'],
            ['booking', 'walk_in_queue_enabled', 'Walk-in queue', 'true', 'boolean'],
            ['booking', 'cancellation_window_hours', 'Cancellation window (hours)', '2', 'number'],
            ['notifications', 'admin_alert_email', 'Admin alert email', 'superadmin@fixtrack.test', 'text'],
            ['notifications', 'email_notifications', 'Email notifications', 'true', 'boolean'],
            ['notifications', 'sms_notifications', 'SMS notifications', 'false', 'boolean'],
            ['security', 'session_timeout_minutes', 'Session timeout (minutes)', '120', 'number'],
            ['security', 'require_staff_mfa', 'Require staff MFA', 'false', 'boolean'],
            ['security', 'login_attempt_limit', 'Login attempt limit', '5', 'number'],
            ['finance', 'currency', 'Platform currency', 'PHP', 'text'],
            ['finance', 'platform_fee_percent', 'Platform service fee (%)', '10', 'number'],
            ['finance', 'refund_window_days', 'Refund window (days)', '7', 'number'],
            ['quality', 'review_moderation', 'Review moderation', 'true', 'boolean'],
            ['quality', 'low_rating_threshold', 'Low-rating alert threshold', '2', 'number'],
        ])->map(fn (array $setting): array => [
            'group' => $setting[0],
            'key' => $setting[1],
            'label' => $setting[2],
            'value' => $setting[3],
            'type' => $setting[4],
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        $inserted = 0;

        foreach ($defaults as $default) {
            $setting = PlatformSetting::query()->firstOrCreate(['key' => $default['key']], $default);
            $inserted += $setting->wasRecentlyCreated ? 1 : 0;
        }

        $this->recordAudit('settings.initialized', 'platform_settings', null, ['inserted' => $inserted]);
        $this->flashStatus($inserted > 0 ? "{$inserted} platform control(s) initialized." : 'Platform controls are already initialized.');
    }

    public function openSettingEditor(int $settingId): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->tableExists('platform_settings'), 422, 'Platform settings are not available yet.');

        $setting = PlatformSetting::query()->findOrFail($settingId);

        $this->editingSettingId = (int) $setting->id;
        $this->settingLabel = (string) $setting->label;
        $this->settingValue = (string) ($setting->value ?? '');
        $this->settingType = (string) $setting->type;
        $this->showSettingEditor = true;
    }

    public function saveSetting(): void
    {
        $this->authorizeSuperAdmin();
        abort_unless($this->editingSettingId, 422, 'A setting must be selected.');

        $setting = PlatformSetting::query()->findOrFail($this->editingSettingId);

        $rules = ['value' => ['required', 'string', 'max:2000']];
        if ($setting->type === 'boolean') {
            $rules['value'][] = Rule::in(['true', 'false']);
        }
        if ($setting->type === 'number') {
            $rules['value'][] = 'numeric';
            $rules['value'][] = 'min:0';
        }
        if ($setting->key === 'admin_alert_email') {
            $rules['value'][] = 'email';
        }

        $value = Validator::make(['value' => trim($this->settingValue)], $rules)->validate()['value'];
        $setting->update(['value' => $value]);
        $this->recordAudit('settings.updated', 'platform_setting', (int) $setting->id, ['key' => $setting->key, 'value' => $value]);
        $this->cancelSettingEditor();
        $this->flashStatus('Platform setting updated.');
    }

    public function cancelSettingEditor(): void
    {
        $this->showSettingEditor = false;
        $this->editingSettingId = null;
        $this->settingLabel = '';
        $this->settingValue = '';
        $this->settingType = '';
    }

    /** @return array<string, mixed> */
    private function settingsContent(): array
    {
        $rows = [];
        $rowIds = [];
        $total = 0;
        $groups = 0;
        $booleans = 0;
        $numbers = 0;

        if ($this->tableExists('platform_settings')) {
            $settings = PlatformSetting::query()->orderBy('group')->orderBy('label')->get();
            $total = $settings->count();
            $groups = $settings->pluck('group')->unique()->count();
            $booleans = $settings->where('type', 'boolean')->count();
            $numbers = $settings->where('type', 'number')->count();
            $rows = $settings->map(function (object $setting) use (&$rowIds): array {
                $rowIds[] = (int) $setting->id;

                return [
                    Str::headline((string) $setting->group),
                    (string) $setting->label,
                    (string) $setting->key,
                    (string) ($setting->value ?? '—'),
                    Str::headline((string) $setting->type),
                ];
            })->all();
        }

        return [
            'stats' => [
                ['label' => 'Total controls', 'value' => Number::format($total), 'icon' => 'cog-6-tooth'],
                ['label' => 'Control groups', 'value' => Number::format($groups), 'icon' => 'squares-2x2'],
                ['label' => 'Boolean controls', 'value' => Number::format($booleans), 'icon' => 'adjustments-horizontal'],
                ['label' => 'Numeric controls', 'value' => Number::format($numbers), 'icon' => 'calculator'],
            ],
            'sections' => [[
                'title' => 'Platform controls',
                'subtitle' => 'These values are read by customer booking, dispatch, finance, and quality workflows.',
                'columns' => [
                    $this->tableColumn('group', 'Group', 'category'),
                    $this->tableColumn('setting', 'Setting'),
                    $this->tableColumn('key', 'Key', 'mono'),
                    $this->tableColumn('value', 'Value'),
                    $this->tableColumn('type', 'Type', 'category'),
                ],
                'rows' => $rows,
                'action' => 'settings',
                'rowIds' => $rowIds,
                'empty' => 'Initialize the default platform controls to connect booking and dispatch behavior.',
            ]],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function systemHealthContent(): array
    {
        $pendingJobs = $this->tableExists('jobs') ? DB::table('jobs')->count() : 0;
        $failedJobs = $this->tableExists('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $databaseStatus = $this->tableExists('users') ? 'Ready' : 'Pending';

        return [
            'stats' => [
                ['label' => 'Application', 'value' => 'Online', 'icon' => 'server-stack'],
                ['label' => 'Pending jobs', 'value' => Number::format($pendingJobs), 'icon' => 'queue-list'],
                ['label' => 'Failed jobs', 'value' => Number::format($failedJobs), 'icon' => 'exclamation-triangle'],
                ['label' => 'Disk usage', 'value' => $this->diskUsage(), 'icon' => 'circle-stack'],
            ],
            'sections' => [
                [
                    'title' => 'Service checks',
                    'subtitle' => 'Core infrastructure and safeguards.',
                    'columns' => [
                        $this->tableColumn('service', 'Service'),
                        $this->tableColumn('status', 'Status', 'status'),
                        $this->tableColumn('detail', 'Detail'),
                        $this->tableColumn('value', 'Value'),
                    ],
                    'rows' => [
                        ['Application', 'Online', 'Laravel request pipeline', app()->version()],
                        ['Database', $databaseStatus, 'Operations schema availability', $databaseStatus === 'Ready' ? 'Connected' : 'Not connected'],
                        ['Queue pipeline', $failedJobs > 0 ? 'Attention' : 'Healthy', 'Pending and failed jobs', "{$pendingJobs} pending · {$failedJobs} failed"],
                    ],
                    'empty' => 'No service checks are available yet.',
                ],
                [
                    'title' => 'Resource snapshot',
                    'subtitle' => 'Current application process and environment.',
                    'columns' => [
                        $this->tableColumn('metric', 'Metric'),
                        $this->tableColumn('value', 'Value'),
                        $this->tableColumn('detail', 'Detail'),
                    ],
                    'rows' => [
                        ['PHP version', PHP_VERSION, 'Runtime'],
                        ['Environment', (string) app()->environment(), 'Application environment'],
                        ['Queue driver', (string) config('queue.default'), 'Configured queue connection'],
                        ['Debug mode', config('app.debug') ? 'Enabled' : 'Disabled', 'Runtime safety setting'],
                    ],
                    'empty' => 'No resource metrics are available yet.',
                ],
            ],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function usersContent(): array
    {
        $total = $this->tableExists('users') ? User::query()->count() : 0;
        $hasAccountStatus = $this->columnExists('users', 'account_status');
        $active = $hasAccountStatus ? User::query()->where('account_status', 'active')->count() : $total;
        $suspended = $hasAccountStatus ? User::query()->where('account_status', 'suspended')->count() : 0;
        $staff = User::query()->whereNotIn('role', ['customer', 'technician'])->count();
        $rows = [];
        $rowIds = [];

        if ($this->tableExists('users')) {
            $columns = ['id', 'name', 'email', 'role', 'email_verified_at', 'created_at'];
            if ($hasAccountStatus) {
                $columns[] = 'account_status';
            }
            if ($this->columnExists('users', 'last_login_at')) {
                $columns[] = 'last_login_at';
            }

            $rows = User::query()->latest('created_at')->limit(12)->get($columns)->map(
                function (object $user) use (&$rowIds): array {
                    $rowIds[] = (int) $user->id;

                    return [
                        (string) $user->name,
                        Str::headline((string) ($user->role ?? 'customer')),
                        Str::headline((string) ($user->account_status ?? 'active')),
                        $user->email_verified_at ? 'Verified' : 'Unverified',
                        $this->formatDate($user->last_login_at ?? null),
                    ];
                },
            )->all();
        }

        return [
            'stats' => [
                ['label' => 'Total accounts', 'value' => Number::format($total), 'icon' => 'users'],
                ['label' => 'Active', 'value' => Number::format($active), 'icon' => 'check-circle'],
                ['label' => 'Suspended', 'value' => Number::format($suspended), 'icon' => 'no-symbol'],
                ['label' => 'Staff accounts', 'value' => Number::format($staff), 'icon' => 'identification'],
            ],
            'sections' => [[
                'title' => 'User directory',
                'subtitle' => 'Manage access, roles, and active sessions.',
                'columns' => [
                    $this->tableColumn('user', 'User'),
                    $this->tableColumn('role', 'Role', 'role'),
                    $this->tableColumn('status', 'Status', 'status'),
                    $this->tableColumn('verification', 'Verification', 'verification'),
                    $this->tableColumn('last_login', 'Last login', 'date'),
                ],
                'rows' => $rows,
                'action' => 'users',
                'rowIds' => $rowIds,
                'empty' => 'No user accounts found.',
            ]],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function verificationContent(): array
    {
        $pending = 0;
        $requested = 0;
        $approved = 0;
        $highRisk = 0;
        $rows = [];
        $rowIds = [];

        if ($this->tableExists('technician_verifications')) {
            $pending = TechnicianVerification::query()->whereIn('status', ['submitted', 'under_review'])->count();
            $requested = TechnicianVerification::query()->where('status', 'information_requested')->count();
            $approved = TechnicianVerification::query()->where('status', 'approved')->count();
            $highRisk = TechnicianVerification::query()->where('risk_level', 'high')->count();
            $rows = TechnicianVerification::query()
                ->with('technician:id,name,email')
                ->latest('submitted_at')
                ->limit(12)
                ->get(['id', 'user_id', 'service_categories', 'years_experience', 'submitted_at', 'risk_level', 'status'])
                ->map(function (object $verification) use (&$rowIds): array {
                    $rowIds[] = (int) $verification->id;

                    return [
                        (string) ($verification->technician?->name ?? '—'),
                        $this->listValue($verification->service_categories),
                        "{$verification->years_experience} years",
                        $this->formatDate($verification->submitted_at),
                        Str::headline((string) $verification->risk_level),
                        Str::headline((string) $verification->status),
                    ];
                })->all();
        }

        return [
            'stats' => [
                ['label' => 'Pending review', 'value' => Number::format($pending), 'icon' => 'clock'],
                ['label' => 'Information requested', 'value' => Number::format($requested), 'icon' => 'chat-bubble-left-right'],
                ['label' => 'Approved', 'value' => Number::format($approved), 'icon' => 'check-circle'],
                ['label' => 'High risk', 'value' => Number::format($highRisk), 'icon' => 'exclamation-triangle'],
            ],
            'sections' => [[
                'title' => 'Verification queue',
                'subtitle' => 'Review technician identities, documents, and approval status.',
                'columns' => [
                    $this->tableColumn('applicant', 'Applicant'),
                    $this->tableColumn('services', 'Services', 'chips'),
                    $this->tableColumn('experience', 'Experience', 'metric'),
                    $this->tableColumn('submitted', 'Submitted', 'date'),
                    $this->tableColumn('risk', 'Risk', 'risk'),
                    $this->tableColumn('status', 'Status', 'status'),
                ],
                'rows' => $rows,
                'action' => 'verification',
                'rowIds' => $rowIds,
                'empty' => 'Submitted technician applications will appear here.',
            ]],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function bookingsContent(): array
    {
        $total = $this->tableExists('bookings') ? Booking::query()->count() : 0;
        $activeStatuses = ['pending', 'matching', 'assigned', 'en_route', 'in_progress'];
        $active = $this->tableExists('bookings') ? Booking::query()->whereIn('status', $activeStatuses)->count() : 0;
        $unassigned = $this->columnExists('bookings', 'assigned_technician_id')
            ? Booking::query()->whereNull('assigned_technician_id')->whereIn('status', $activeStatuses)->count()
            : 0;
        $completed = $this->tableExists('bookings') ? Booking::query()->where('status', 'completed')->count() : 0;
        $cancelled = $this->tableExists('bookings') ? Booking::query()->where('status', 'cancelled')->count() : 0;
        $rows = [];
        $rowIds = [];

        if ($this->tableExists('bookings')) {
            $query = Booking::query()->with(['customer:id,name', 'technician:id,name'])->latest('created_at')->limit(12);
            $select = ['id', 'user_id', 'reference', 'customer_name', 'service_type', 'booking_type', 'scheduled_at', 'status', 'assigned_technician_id'];
            if ($this->columnExists('bookings', 'is_priority')) {
                $select[] = 'is_priority';
            }
            $rows = $query->get($select)->map(function (Booking $booking) use (&$rowIds): array {
                $rowIds[] = (int) $booking->id;

                return [
                    (string) $booking->reference.($booking->is_priority ?? false ? ' · Priority' : ''),
                    (string) ($booking->customer?->name ?? '—'),
                    $this->serviceLabel($booking->service_type),
                    $booking->scheduled_at ? $this->formatDate($booking->scheduled_at) : 'ASAP',
                    (string) ($booking->technician?->name ?? 'Unassigned'),
                    Str::headline((string) $booking->status),
                ];
            })->all();
        }

        return [
            'stats' => [
                ['label' => 'Total bookings', 'value' => Number::format($total), 'icon' => 'clipboard-document-list'],
                ['label' => 'Active', 'value' => Number::format($active), 'icon' => 'arrow-path'],
                ['label' => 'Unassigned', 'value' => Number::format($unassigned), 'icon' => 'user-minus'],
                ['label' => 'Completed', 'value' => Number::format($completed), 'icon' => 'check-circle'],
                ['label' => 'Cancelled', 'value' => Number::format($cancelled), 'icon' => 'x-circle'],
            ],
            'sections' => [[
                'title' => 'Service booking registry',
                'subtitle' => 'Manage the booking lifecycle and exception cases.',
                'columns' => [
                    $this->tableColumn('booking', 'Booking', 'booking'),
                    $this->tableColumn('customer', 'Customer'),
                    $this->tableColumn('service', 'Service'),
                    $this->tableColumn('schedule', 'Schedule', 'date'),
                    $this->tableColumn('technician', 'Technician', 'assignment'),
                    $this->tableColumn('status', 'Status', 'status'),
                ],
                'rows' => $rows,
                'action' => 'bookings',
                'rowIds' => $rowIds,
                'empty' => 'No service bookings yet.',
            ]],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function dispatchContent(): array
    {
        $hasAssignments = $this->columnExists('bookings', 'assigned_technician_id');
        $queue = [];
        $technicians = [];
        $technicianOptions = [];
        $queueRowIds = [];
        $technicianRowIds = [];
        $unassigned = 0;
        $activeAssignments = 0;

        if ($this->tableExists('bookings')) {
            $queueQuery = Booking::query()->with(['customer:id,name', 'technician:id,name'])->whereIn('status', ['pending', 'matching', 'assigned'])->oldest('created_at')->limit(12);
            $select = ['id', 'user_id', 'reference', 'customer_name', 'service_type', 'address', 'status', 'assigned_technician_id'];
            if ($hasAssignments) {
                $unassigned = Booking::query()->whereNull('assigned_technician_id')->whereIn('status', ['pending', 'matching', 'assigned'])->count();
                $activeAssignments = Booking::query()->whereNotNull('assigned_technician_id')->whereIn('status', ['assigned', 'en_route', 'in_progress'])->count();
            }
            $queue = $queueQuery->get($select)->map(function (Booking $booking) use (&$queueRowIds): array {
                $queueRowIds[] = (int) $booking->id;

                return [
                    (string) $booking->reference,
                    (string) ($booking->customer?->name ?? '—'),
                    $this->serviceLabel($booking->service_type),
                    Str::limit((string) $booking->address, 36),
                    (string) ($booking->technician?->name ?? 'Unassigned'),
                    Str::headline((string) $booking->status),
                ];
            })->all();
        }

        if ($this->tableExists('users')) {
            $select = ['id', 'name', 'email'];
            foreach (['availability_status', 'last_seen_at'] as $column) {
                if ($this->columnExists('users', $column)) {
                    $select[] = $column;
                }
            }
            $technicians = User::query()
                ->select($select)
                ->where('role', 'technician')
                ->withCount(['assignedBookings as active_jobs_count' => fn ($query) => $query->whereIn('status', ['assigned', 'en_route', 'in_progress'])])
                ->latest('name')
                ->limit(12)
                ->get()->map(function (object $technician) use (&$technicianRowIds): array {
                    $technicianRowIds[] = (int) $technician->id;

                    return [
                        (string) $technician->name,
                        Str::headline((string) ($technician->availability_status ?? 'Unknown')),
                        Number::format((int) $technician->active_jobs_count),
                        $this->formatDate($technician->last_seen_at ?? null),
                    ];
                })->all();

            if ($this->tableExists('technician_verifications')) {
                $technicianOptions = User::query()
                    ->where('role', 'technician')
                    ->whereHas('technicianVerification', fn ($query) => $query->where('status', 'approved'))
                    ->when($this->columnExists('users', 'account_status'), fn ($query) => $query->where('account_status', 'active'))
                    ->orderBy('name')
                    ->get(['id', 'name', 'availability_status'])
                    ->map(fn (object $technician): array => [
                        'id' => (int) $technician->id,
                        'name' => (string) $technician->name,
                        'availability' => Str::headline((string) ($technician->availability_status ?? 'unknown')),
                    ])->all();
            }
        }

        $available = $this->columnExists('users', 'availability_status') && $this->tableExists('users') ? User::query()->where('role', 'technician')->where('availability_status', 'available')->count() : 0;
        $busy = $this->columnExists('users', 'availability_status') && $this->tableExists('users') ? User::query()->where('role', 'technician')->where('availability_status', 'busy')->count() : 0;

        return [
            'stats' => [
                ['label' => 'Unassigned', 'value' => Number::format($unassigned), 'icon' => 'user-minus'],
                ['label' => 'Available technicians', 'value' => Number::format($available), 'icon' => 'user-plus'],
                ['label' => 'Busy technicians', 'value' => Number::format($busy), 'icon' => 'bolt'],
                ['label' => 'Active assignments', 'value' => Number::format($activeAssignments), 'icon' => 'map'],
            ],
            'technicianOptions' => $technicianOptions,
            'sections' => [
                [
                    'title' => 'Live dispatch queue',
                    'subtitle' => 'Requests awaiting matching, assignment, or coordination.',
                    'columns' => [
                        $this->tableColumn('request', 'Request'),
                        $this->tableColumn('customer', 'Customer'),
                        $this->tableColumn('service', 'Service'),
                        $this->tableColumn('location', 'Location'),
                        $this->tableColumn('technician', 'Technician', 'assignment'),
                        $this->tableColumn('status', 'Status', 'status'),
                    ],
                    'rows' => $queue,
                    'action' => 'dispatch-bookings',
                    'rowIds' => $queueRowIds,
                    'empty' => 'New service requests awaiting coordination will appear here.',
                ],
                [
                    'title' => 'Technician availability',
                    'subtitle' => 'Verified workforce status and assignment load.',
                    'columns' => [
                        $this->tableColumn('technician', 'Technician'),
                        $this->tableColumn('availability', 'Availability', 'availability'),
                        $this->tableColumn('active_jobs', 'Active jobs', 'metric'),
                        $this->tableColumn('last_seen', 'Last seen', 'date'),
                    ],
                    'rows' => $technicians,
                    'action' => 'technicians',
                    'rowIds' => $technicianRowIds,
                    'empty' => 'Verified technicians will appear here.',
                ],
            ],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function walkInContent(): array
    {
        $rows = [];
        $rowIds = [];
        $counters = [];
        $waiting = 0;
        $serving = 0;
        $completed = 0;
        $availableCounters = 0;
        $averageWait = 0;

        if ($this->tableExists('walk_in_entries')) {
            $waiting = WalkInEntry::query()->where('status', 'waiting')->count();
            $serving = WalkInEntry::query()->where('status', 'serving')->count();
            $completed = WalkInEntry::query()->where('status', 'completed')->whereDate('completed_at', today())->count();
            $waitingEntries = WalkInEntry::query()->where('status', 'waiting')->pluck('checked_in_at')->map(fn (mixed $checkedIn): float => max(0, now()->diffInMinutes(Carbon::parse($checkedIn))))->all();
            $averageWait = count($waitingEntries) ? (int) round(array_sum($waitingEntries) / count($waitingEntries)) : 0;
            $with = ['counter:id,name'];
            $select = ['id', 'queue_number', 'customer_name', 'service_type', 'priority', 'checked_in_at', 'status', 'counter_id'];
            if ($this->columnExists('walk_in_entries', 'user_id')) {
                $with[] = 'customer:id,name';
                $select[] = 'user_id';
            }
            $rows = WalkInEntry::query()
                ->with($with)
                ->latest('checked_in_at')
                ->limit(12)
                ->get($select)
                ->map(function (WalkInEntry $entry) use (&$rowIds): array {
                    $rowIds[] = (int) $entry->id;

                    return [
                        (string) $entry->queue_number,
                        (string) ($entry->customer?->name ?? $entry->customer_name),
                        $this->serviceLabel($entry->service_type),
                        Str::headline((string) $entry->priority),
                        $this->formatTime($entry->checked_in_at),
                        "{$this->waitMinutes($entry->checked_in_at)} min",
                        (string) ($entry->counter?->name ?? 'Unassigned'),
                        Str::headline((string) $entry->status),
                    ];
                })->all();
        }

        if ($this->tableExists('service_counters')) {
            $availableCounters = ServiceCounter::query()->where('status', 'available')->count();
            $counters = ServiceCounter::query()->latest('name')->limit(12)->get(['name', 'status'])->map(fn (object $counter): array => [
                (string) $counter->name,
                Str::headline((string) $counter->status),
            ])->all();
        }

        return [
            'stats' => [
                ['label' => 'Waiting', 'value' => Number::format($waiting), 'icon' => 'clock'],
                ['label' => 'Being served', 'value' => Number::format($serving), 'icon' => 'arrow-path'],
                ['label' => 'Completed today', 'value' => Number::format($completed), 'icon' => 'check-circle'],
                ['label' => 'Average wait', 'value' => "{$averageWait} min", 'icon' => 'clock'],
                ['label' => 'Available counters', 'value' => Number::format($availableCounters), 'icon' => 'building-storefront'],
            ],
            'sections' => [
                [
                    'title' => 'Live walk-in queue',
                    'subtitle' => 'Queue volume, waiting time, and service progress.',
                    'columns' => [
                        $this->tableColumn('queue', 'Queue'),
                        $this->tableColumn('customer', 'Customer'),
                        $this->tableColumn('service', 'Service'),
                        $this->tableColumn('priority', 'Priority', 'priority'),
                        $this->tableColumn('check_in', 'Check-in', 'date'),
                        $this->tableColumn('wait_time', 'Wait time', 'wait'),
                        $this->tableColumn('counter', 'Counter', 'assignment'),
                        $this->tableColumn('status', 'Status', 'status'),
                    ],
                    'rows' => $rows,
                    'action' => 'walk-in',
                    'rowIds' => $rowIds,
                    'empty' => 'No walk-in requests yet.',
                ],
                [
                    'title' => 'Service counters',
                    'subtitle' => 'Current counter availability.',
                    'columns' => [
                        $this->tableColumn('counter', 'Counter'),
                        $this->tableColumn('status', 'Status', 'status'),
                    ],
                    'rows' => $counters,
                    'empty' => 'Service counters will appear here.',
                ],
            ],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function paymentsContent(): array
    {
        $rows = [];
        $rowIds = [];
        $metricsVersion = $this->latestDataVersion(['payments', 'bookings']);
        $metrics = Cache::remember(
            "fixtrack:payments:metrics:{$metricsVersion}",
            now()->addSeconds(30),
            function (): array {
                if (! $this->tableExists('payments')) {
                    return ['totalCollected' => 0.0, 'pending' => 0, 'failed' => 0, 'refunded' => 0];
                }

                return [
                    'totalCollected' => $this->tableExists('bookings')
                        ? (float) Payment::query()
                            ->where('status', 'paid')
                            ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->where('status', 'completed'))
                            ->sum('amount')
                        : 0.0,
                    'pending' => Payment::query()->where('status', 'pending')->count(),
                    'failed' => Payment::query()->where('status', 'failed')->count(),
                    'refunded' => Payment::query()->where('status', 'refunded')->count(),
                ];
            },
        );

        if ($this->tableExists('payments')) {
            $payments = Payment::query()
                ->when($this->tableExists('bookings'), fn ($query) => $query->with('booking:id,user_id,reference,customer_name'))
                ->when($this->tableExists('users'), fn ($query) => $query->with('booking.customer:id,name'))
                ->latest('created_at')
                ->limit(12)
                ->get();
            $rows = $payments->map(function (Payment $payment) use (&$rowIds): array {
                $rowIds[] = (int) $payment->id;

                return [
                    (string) ($payment->booking?->reference ?? '—'),
                    (string) ($payment->booking?->customer?->name ?? $payment->booking?->customer_name ?? '—'),
                    'PHP '.Number::format((float) $payment->amount, 2),
                    (string) ($payment->method ?: '—'),
                    Str::headline((string) $payment->status),
                    $this->formatDate($payment->created_at),
                ];
            })->all();
        }

        return [
            'stats' => [
                ['label' => 'Total collected', 'value' => 'PHP '.Number::format($metrics['totalCollected'], 2), 'icon' => 'banknotes'],
                ['label' => 'Pending', 'value' => Number::format($metrics['pending']), 'icon' => 'clock'],
                ['label' => 'Failed', 'value' => Number::format($metrics['failed']), 'icon' => 'exclamation-triangle'],
                ['label' => 'Refunded', 'value' => Number::format($metrics['refunded']), 'icon' => 'arrow-uturn-left'],
            ],
            'sections' => [[
                'title' => 'Payment transactions',
                'subtitle' => 'Review payment activity, revenue, refunds, and exceptions.',
                'columns' => [
                    $this->tableColumn('booking', 'Booking'),
                    $this->tableColumn('customer', 'Customer'),
                    $this->tableColumn('amount', 'Amount', 'money'),
                    $this->tableColumn('method', 'Method'),
                    $this->tableColumn('status', 'Status', 'status'),
                    $this->tableColumn('created', 'Created', 'date'),
                ],
                'rows' => $rows,
                'action' => 'payments',
                'rowIds' => $rowIds,
                'empty' => 'No payment transactions yet.',
            ]],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function ratingsContent(): array
    {
        $rows = [];
        $rowIds = [];
        $average = 0.0;
        $published = 0;
        $flagged = 0;
        $hidden = 0;

        if ($this->tableExists('reviews')) {
            $average = (float) (Review::query()->avg('rating') ?? 0);
            $published = Review::query()->where('status', 'published')->count();
            $flagged = Review::query()->where('status', 'flagged')->count();
            $hidden = Review::query()->where('status', 'hidden')->count();
            $rows = Review::query()
                ->with(['customer:id,name', 'technician:id,name'])
                ->latest('created_at')
                ->limit(12)
                ->get()
                ->map(function (Review $review) use (&$rowIds): array {
                    $rowIds[] = (int) $review->id;

                    return [
                        (string) ($review->customer?->name ?? '—'),
                        (string) ($review->technician?->name ?? '—'),
                        "{$review->rating} / 5",
                        Str::limit((string) ($review->comment ?: '—'), 48),
                        Str::headline((string) $review->status),
                        $this->formatDate($review->created_at),
                    ];
                })->all();
        }

        return [
            'stats' => [
                ['label' => 'Average rating', 'value' => "{$average} / 5", 'icon' => 'star'],
                ['label' => 'Published', 'value' => Number::format($published), 'icon' => 'eye'],
                ['label' => 'Flagged', 'value' => Number::format($flagged), 'icon' => 'flag'],
                ['label' => 'Hidden', 'value' => Number::format($hidden), 'icon' => 'eye-slash'],
            ],
            'sections' => [[
                'title' => 'Ratings & reviews',
                'subtitle' => 'Monitor service quality, ratings, and technician feedback.',
                'columns' => [
                    $this->tableColumn('customer', 'Customer'),
                    $this->tableColumn('technician', 'Technician'),
                    $this->tableColumn('rating', 'Rating', 'rating'),
                    $this->tableColumn('review', 'Review'),
                    $this->tableColumn('status', 'Status', 'status'),
                    $this->tableColumn('created', 'Created', 'date'),
                ],
                'rows' => $rows,
                'action' => 'reviews',
                'rowIds' => $rowIds,
                'empty' => 'Customer ratings and reviews will appear here.',
            ]],
        ];
    }

    /**
     * @return array{
     *     stats: array<int, array{label: string, value: string, icon: string}>,
     *     chart: array{
     *         days: array<int, array{label: string, active: int, completed: int, cancelled: int, x: int, activeY: int, completedY: int, cancelledY: int}>,
     *         maxValue: int,
     *         points: array{active: string, completed: string, cancelled: string},
     *         areas: array{active: string, completed: string, cancelled: string},
     *     },
     *     sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>,
     * }
     */
    private function analyticsContent(): array
    {
        $metricsVersion = $this->latestDataVersion(['bookings', 'payments', 'reviews']);
        $metrics = Cache::remember(
            "fixtrack:analytics:metrics:{$metricsVersion}",
            now()->addSeconds(30),
            function (): array {
                $total = $this->tableExists('bookings') ? Booking::query()->count() : 0;
                $completed = $this->tableExists('bookings') ? Booking::query()->where('status', 'completed')->count() : 0;
                $cancelled = $this->tableExists('bookings') ? Booking::query()->where('status', 'cancelled')->count() : 0;
                $revenue = $this->tableExists('payments') && $this->tableExists('bookings')
                    ? (float) Payment::query()
                        ->where('payments.status', 'paid')
                        ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->where('status', 'completed'))
                        ->sum('payments.amount')
                    : 0.0;
                $averageRating = $this->tableExists('reviews')
                    ? (float) (Review::query()->where('status', 'published')->avg('rating') ?? 0)
                    : 0.0;

                return compact('total', 'completed', 'cancelled', 'revenue', 'averageRating');
            },
        );
        $total = $metrics['total'];
        $completed = $metrics['completed'];
        $cancelled = $metrics['cancelled'];
        $revenue = $metrics['revenue'];
        $averageRating = $metrics['averageRating'];
        $performance = [];
        $services = [];
        $chartDays = [];

        foreach (range(6, 0) as $daysAgo) {
            $date = now()->subDays($daysAgo);
            $chartDays[$date->toDateString()] = [
                'label' => $date->format('D'),
                'active' => 0,
                'completed' => 0,
                'cancelled' => 0,
            ];
        }

        if ($this->tableExists('bookings')) {
            $daily = Booking::query()
                ->selectRaw('DATE(created_at) as day, status, COUNT(*) as total')
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->groupByRaw('DATE(created_at), status')
                ->get()
                ->groupBy('day');

            foreach ($chartDays as $dayKey => $day) {
                $dayRows = $daily->get($dayKey, collect());
                $active = (int) $dayRows->whereIn('status', ['pending', 'matching', 'assigned', 'en_route', 'in_progress'])->sum('total');
                $dayCompleted = (int) $dayRows->where('status', 'completed')->sum('total');
                $dayCancelled = (int) $dayRows->where('status', 'cancelled')->sum('total');
                $chartDays[$dayKey]['active'] = $active;
                $chartDays[$dayKey]['completed'] = $dayCompleted;
                $chartDays[$dayKey]['cancelled'] = $dayCancelled;
                $performance[] = [$day['label'], Number::format($active + $dayCompleted + $dayCancelled), Number::format($active), Number::format($dayCompleted), Number::format($dayCancelled)];
            }
            $services = Booking::query()
                ->select('service_type')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('service_type')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn (Booking $service): array => [$this->serviceLabel($service->service_type), Number::format((int) $service->total)])
                ->all();
        }
        $chartMaxValue = max(1, ...array_map(
            fn (array $day): int => max($day['active'], $day['completed'], $day['cancelled']),
            array_values($chartDays),
        ));
        $chartDays = array_values($chartDays);

        foreach ($chartDays as $index => &$day) {
            $day['x'] = 44 + $index * 102;
            $day['activeY'] = 180 - (int) round($day['active'] / $chartMaxValue * 144);
            $day['completedY'] = 180 - (int) round($day['completed'] / $chartMaxValue * 144);
            $day['cancelledY'] = 180 - (int) round($day['cancelled'] / $chartMaxValue * 144);
        }
        unset($day);

        $chartPoints = [];

        foreach (['active', 'completed', 'cancelled'] as $series) {
            $chartPoints[$series] = implode(' ', array_map(
                fn (array $day): string => $day['x'].','.$day[$series.'Y'],
                $chartDays,
            ));
        }

        $chartAreas = [];

        foreach (['active', 'completed', 'cancelled'] as $series) {
            $chartAreas[$series] = implode(' ', array_merge(
                [$chartDays[0]['x'].',180'],
                array_map(
                    fn (array $day): string => $day['x'].','.$day[$series.'Y'],
                    $chartDays,
                ),
                [$chartDays[count($chartDays) - 1]['x'].',180'],
            ));
        }

        return [
            'stats' => [
                ['label' => 'Completion rate', 'value' => Number::format($total > 0 ? $completed / $total * 100 : 0, 1).'%', 'icon' => 'chart-bar'],
                ['label' => 'Cancellation rate', 'value' => Number::format($total > 0 ? $cancelled / $total * 100 : 0, 1).'%', 'icon' => 'chart-pie'],
                ['label' => 'Total revenue', 'value' => 'PHP '.Number::format($revenue, 2), 'icon' => 'banknotes'],
                ['label' => 'Average rating', 'value' => "{$averageRating} / 5", 'icon' => 'star'],
            ],
            'chart' => [
                'days' => $chartDays,
                'maxValue' => $chartMaxValue,
                'points' => [
                    'active' => $chartPoints['active'],
                    'completed' => $chartPoints['completed'],
                    'cancelled' => $chartPoints['cancelled'],
                ],
                'areas' => [
                    'active' => $chartAreas['active'],
                    'completed' => $chartAreas['completed'],
                    'cancelled' => $chartAreas['cancelled'],
                ],
            ],
            'sections' => [
                [
                    'title' => 'Booking performance',
                    'subtitle' => 'Seven-day operational activity.',
                    'columns' => [
                        $this->tableColumn('day', 'Day'),
                        $this->tableColumn('total', 'Total', 'metric'),
                        $this->tableColumn('active', 'Active', 'metric'),
                        $this->tableColumn('completed', 'Completed', 'metric'),
                        $this->tableColumn('cancelled', 'Cancelled', 'metric'),
                    ],
                    'rows' => $performance,
                    'empty' => 'No booking performance data yet.',
                ],
                [
                    'title' => 'Top services',
                    'subtitle' => 'Most requested service types.',
                    'columns' => [
                        $this->tableColumn('service', 'Service'),
                        $this->tableColumn('bookings', 'Bookings', 'metric'),
                    ],
                    'rows' => $services,
                    'empty' => 'Service demand will appear here.',
                ],
            ],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function supportContent(): array
    {
        $rows = [];
        $rowIds = [];
        $open = 0;
        $inProgress = 0;
        $urgent = 0;
        $resolved = 0;

        if ($this->tableExists('support_tickets')) {
            $open = SupportTicket::query()->where('status', 'open')->count();
            $inProgress = SupportTicket::query()->where('status', 'in_progress')->count();
            $urgent = SupportTicket::query()->where('priority', 'urgent')->count();
            $resolved = SupportTicket::query()->where('status', 'resolved')->count();
            $rows = SupportTicket::query()
                ->with('requester:id,name')
                ->latest('updated_at')
                ->limit(12)
                ->get()
                ->map(function (SupportTicket $ticket) use (&$rowIds): array {
                    $rowIds[] = (int) $ticket->id;

                    return [
                        (string) $ticket->reference,
                        (string) ($ticket->requester?->name ?? '—'),
                        Str::limit((string) $ticket->subject, 42),
                        Str::headline((string) $ticket->category),
                        Str::headline((string) $ticket->priority),
                        Str::headline((string) $ticket->status),
                        $this->formatDate($ticket->updated_at),
                    ];
                })->all();
        }

        return [
            'stats' => [
                ['label' => 'Open tickets', 'value' => Number::format($open), 'icon' => 'chat-bubble-left-right'],
                ['label' => 'In progress', 'value' => Number::format($inProgress), 'icon' => 'arrow-path'],
                ['label' => 'Urgent', 'value' => Number::format($urgent), 'icon' => 'exclamation-triangle'],
                ['label' => 'Resolved', 'value' => Number::format($resolved), 'icon' => 'check-circle'],
            ],
            'sections' => [[
                'title' => 'Support queue',
                'subtitle' => 'Review escalated support cases, complaints, and disputes.',
                'columns' => [
                    $this->tableColumn('ticket', 'Ticket'),
                    $this->tableColumn('customer', 'Customer'),
                    $this->tableColumn('subject', 'Subject'),
                    $this->tableColumn('category', 'Category', 'category'),
                    $this->tableColumn('priority', 'Priority', 'priority'),
                    $this->tableColumn('status', 'Status', 'status'),
                    $this->tableColumn('updated', 'Updated', 'date'),
                ],
                'rows' => $rows,
                'action' => 'support',
                'rowIds' => $rowIds,
                'empty' => 'Support requests and disputes will appear here.',
            ]],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function auditContent(): array
    {
        $total = 0;
        $today = 0;
        $userChanges = 0;
        $actors = 0;
        $rows = [];

        if ($this->tableExists('audit_logs')) {
            $total = AuditLog::query()->count();
            $today = AuditLog::query()->whereDate('created_at', today())->count();
            $userChanges = AuditLog::query()->where('target_type', 'user')->count();
            $actors = AuditLog::query()->whereNotNull('user_id')->distinct('user_id')->count('user_id');
            $rows = AuditLog::query()
                ->with('actor:id,name')
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    (string) ($log->actor?->name ?? '—'),
                    Str::headline(str_replace('.', ' ', (string) $log->action)),
                    Str::headline((string) $log->target_type).' #'.($log->target_id ?? '—'),
                    (string) ($log->ip_address ?? '—'),
                    $this->formatDateTime($log->created_at),
                ])->all();
        }

        return [
            'stats' => [
                ['label' => 'Total events', 'value' => Number::format($total), 'icon' => 'document-text'],
                ['label' => 'Events today', 'value' => Number::format($today), 'icon' => 'calendar-days'],
                ['label' => 'User changes', 'value' => Number::format($userChanges), 'icon' => 'users'],
                ['label' => 'Unique actors', 'value' => Number::format($actors), 'icon' => 'finger-print'],
            ],
            'sections' => [[
                'title' => 'Audit trail',
                'subtitle' => 'Security-sensitive actions and governance history.',
                'columns' => [
                    $this->tableColumn('actor', 'Actor'),
                    $this->tableColumn('action', 'Action', 'action'),
                    $this->tableColumn('target', 'Target'),
                    $this->tableColumn('ip_address', 'IP address', 'mono'),
                    $this->tableColumn('timestamp', 'Timestamp', 'date'),
                ],
                'rows' => $rows,
                'empty' => 'No audit events yet.',
            ]],
        ];
    }

    /** @return array{stats: array<int, array{label: string, value: string, icon: string}>, sections: array<int, array{title: string, subtitle: string, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<int, string>>, empty: string}>} */
    private function catalogContent(): array
    {
        $total = 0;
        $active = 0;
        $inactive = 0;
        $quotation = 0;
        $rows = [];
        $rowIds = [];

        if ($this->tableExists('service_catalog')) {
            $total = ServiceCatalog::query()->count();
            $active = ServiceCatalog::query()->where('is_active', true)->count();
            $inactive = ServiceCatalog::query()->where('is_active', false)->count();
            $quotation = ServiceCatalog::query()->whereNull('base_price')->count();
            $rows = ServiceCatalog::query()->orderBy('name')->limit(20)->get()->map(function (ServiceCatalog $service) use (&$rowIds): array {
                $rowIds[] = (int) $service->id;

                return [
                    (string) $service->code,
                    (string) $service->name,
                    Str::headline((string) $service->category),
                    $service->base_price !== null ? 'PHP '.Number::format((float) $service->base_price, 2) : 'Quotation',
                    $service->is_active ? 'Active' : 'Inactive',
                ];
            })->all();
        }

        return [
            'stats' => [
                ['label' => 'Total services', 'value' => Number::format($total), 'icon' => 'wrench-screwdriver'],
                ['label' => 'Active services', 'value' => Number::format($active), 'icon' => 'check-circle'],
                ['label' => 'Inactive services', 'value' => Number::format($inactive), 'icon' => 'pause-circle'],
                ['label' => 'Quotation based', 'value' => Number::format($quotation), 'icon' => 'receipt-percent'],
            ],
            'sections' => [[
                'title' => 'Service catalog',
                'subtitle' => 'Govern available services, categories, and pricing rules.',
                'columns' => [
                    $this->tableColumn('code', 'Code', 'mono'),
                    $this->tableColumn('service', 'Service'),
                    $this->tableColumn('category', 'Category', 'category'),
                    $this->tableColumn('base_price', 'Base price', 'price'),
                    $this->tableColumn('status', 'Status', 'status'),
                ],
                'rows' => $rows,
                'action' => 'catalog',
                'rowIds' => $rowIds,
                'empty' => 'No catalog services yet.',
            ]],
        ];
    }

    private function tableExists(string $table): bool
    {
        return $this->tableAvailability[$table] ??= Schema::hasTable($table);
    }

    /** @param array<int, string> $tables */
    private function latestDataVersion(array $tables): string
    {
        $models = [
            'bookings' => Booking::class,
            'payments' => Payment::class,
            'reviews' => Review::class,
            'support_tickets' => SupportTicket::class,
            'service_catalog' => ServiceCatalog::class,
            'service_counters' => ServiceCounter::class,
            'walk_in_entries' => WalkInEntry::class,
            'technician_verifications' => TechnicianVerification::class,
            'users' => User::class,
        ];

        return collect($tables)
            ->map(function (string $table) use ($models): string {
                $model = $models[$table] ?? null;

                return $model !== null && $this->columnExists($table, 'updated_at')
                    ? (string) ($model::query()->max('updated_at') ?? 'none')
                    : 'none';
            })
            ->reject(fn (string $version): bool => $version === 'none')
            ->sortDesc()
            ->first() ?? 'none';
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        return $this->columnAvailability[$key] ??= $this->tableExists($table) && Schema::hasColumn($table, $column);
    }

    private function serviceLabel(mixed $service): string
    {
        $code = (string) $service;

        if (! $this->tableExists('service_catalog')) {
            return Str::headline($code);
        }

        $labels = once(fn (): array => ServiceCatalog::query()->pluck('name', 'code')->all());

        return (string) ($labels[$code] ?? Str::headline($code));
    }

    /** @return array{key: string, label: string, type: string} */
    private function tableColumn(string $key, string $label, string $type = 'text'): array
    {
        return compact('key', 'label', 'type');
    }

    private function listValue(mixed $value): string
    {
        $values = is_string($value) ? json_decode($value, true) : $value;

        return is_array($values) && $values !== []
            ? implode(', ', array_map(fn (mixed $item): string => $this->serviceLabel($item), $values))
            : 'Not provided';
    }

    private function formatDate(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('M j, Y') : '—';
    }

    private function formatDateTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('M j, Y · g:i A') : '—';
    }

    private function formatTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : '—';
    }

    private function waitMinutes(mixed $value): int
    {
        return $value ? max(0, (int) now()->diffInMinutes(Carbon::parse($value))) : 0;
    }

    private function diskUsage(): string
    {
        $total = disk_total_space(storage_path()) ?: 0;
        $free = disk_free_space(storage_path()) ?: 0;

        return $total > 0 ? Number::format((1 - $free / $total) * 100, 1).'%' : '—';
    }
}
