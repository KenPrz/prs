<?php

namespace App\Models;

use App\Concerns\HasNotes;
use App\Concerns\InteractsWithWorkflow;
use App\Concerns\RegistersAttachmentMediaCollection;
use App\Contracts\WorkflowSubject;
use App\Enums\PaymentRequestFormStatus;
use App\Enums\WorkflowInstanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'requestor_id',
    'supplier_id',
    'company_profile_id',
    'workflow_id',
    'workflow_instance_id',
    'description',
    'amount',
    'invoice_number',
    'due_date',
    'stamp_date',
    'status',
    'document_id',
])]
class PaymentRequestForm extends Model implements HasMedia, WorkflowSubject
{
    /** @use HasFactory<PaymentRequestFormFactory> */
    use HasFactory, HasNotes, InteractsWithWorkflow;

    use InteractsWithMedia, LogsActivity, RegistersAttachmentMediaCollection {
        RegistersAttachmentMediaCollection::registerMediaCollections insteadof InteractsWithMedia;
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (PaymentRequestForm $model) {
            $year = now()->year;

            $nextNumber = DB::transaction(function () use ($year) {
                $latestPrfNumber = self::query()
                    ->whereYear('created_at', $year)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->value('prf_number');

                if (! $latestPrfNumber) {
                    return 1;
                }

                $suffix = (int) Str::afterLast($latestPrfNumber, '-');

                return $suffix + 1;
            });

            $model->prf_number = \sprintf('%d-%03d', $year, $nextNumber);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'stamp_date' => 'date',
            'amount' => 'decimal:2',
            'status' => PaymentRequestFormStatus::class,
        ];
    }

    /**
     * Get the options for the activity log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The user who prepared the payment request form.
     *
     * @return BelongsTo<User, PaymentRequestForm>
     */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    /**
     * The payee / supplier for this payment request.
     *
     * @return BelongsTo<Supplier, PaymentRequestForm>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The company profile associated with this payment request.
     *
     * @return BelongsTo<CompanyProfile, PaymentRequestForm>
     */
    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    /**
     * The departments (cost centers) associated with this payment request.
     *
     * @return BelongsToMany<Department, PaymentRequestForm>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'prf_departments')
            ->using(PrfDepartment::class)
            ->withTimestamps();
    }

    // Workflow relations provided by InteractsWithWorkflow.

    public function workflowDocumentType(): string
    {
        return 'PRF';
    }

    public function defaultWorkflowKey(): string
    {
        return 'prf.default';
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveDepartmentHeads(): Collection
    {
        return $this->departments()
            ->with('departmentHead')
            ->get()
            ->map(fn (Department $department) => $department->departmentHead)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function applyWorkflowStarted(): void
    {
        $this->forceFill(['status' => PaymentRequestFormStatus::REVIEWING])->save();
    }

    public function applyWorkflowStatus(WorkflowInstanceStatus $status): void
    {
        $mapped = match ($status) {
            WorkflowInstanceStatus::Approved => PaymentRequestFormStatus::APPROVED,
            WorkflowInstanceStatus::Rejected => PaymentRequestFormStatus::REJECTED,
            WorkflowInstanceStatus::Cancelled => PaymentRequestFormStatus::CANCELLED,
            WorkflowInstanceStatus::Pending => null,
        };

        if ($mapped !== null) {
            $this->forceFill(['status' => $mapped])->save();
        }
    }

    /**
     * The document template associated with this payment request.
     *
     * @return BelongsTo<Document, PaymentRequestForm>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
