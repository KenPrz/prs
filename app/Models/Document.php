<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'resource_class',
    'blade_file',
])]
class Document extends Model implements HasMedia
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('docx_template')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/octet-stream',
            ]);
    }

    public function registerMediaConversions(?Media $media = null): void {}

    /**
     * @return HasMany<PurchaseRequisition, Document>
     */
    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class);
    }

    public static function defaultPurchaseOrderTemplate(): ?self
    {
        return static::defaultTemplateFor(PurchaseOrder::class);
    }

    public static function defaultPurchaseRequisitionTemplate(): ?self
    {
        return static::defaultTemplateFor(PurchaseRequisition::class);
    }

    public static function defaultPaymentRequestFormTemplate(): ?self
    {
        return static::defaultTemplateFor(PaymentRequestForm::class);
    }

    public static function defaultReceivingReportTemplate(): ?self
    {
        return static::defaultTemplateFor(ReceivingReport::class);
    }

    /**
     * Canonical mapping of resource class => {label, blade_file} for every
     * document type the system understands. This is the single source of
     * truth for the admin "Document Templates" config UI and for resolving
     * the default template per resource type.
     *
     * @return array<class-string, array{label: string, blade_file: string}>
     */
    public static function types(): array
    {
        return [
            PurchaseRequisition::class => [
                'label' => 'Purchase Requisition',
                'blade_file' => 'documents.purchase-requisition',
            ],
            PurchaseOrder::class => [
                'label' => 'Purchase Order',
                'blade_file' => 'documents.purchase-order',
            ],
            ReceivingReport::class => [
                'label' => 'Receiving Report',
                'blade_file' => 'documents.receiving-report',
            ],
            PaymentRequestForm::class => [
                'label' => 'Payment Request Form',
                'blade_file' => 'documents.payment-request-form',
            ],
        ];
    }

    /**
     * The friendly label for this document's resource class, falling back
     * to the raw class string for any legacy/unrecognized value.
     */
    public function resourceLabel(): string
    {
        return self::types()[$this->resource_class]['label'] ?? $this->resource_class;
    }

    private static function defaultTemplateFor(string $resourceClass): ?self
    {
        return static::query()
            ->where('resource_class', $resourceClass)
            ->where('blade_file', self::types()[$resourceClass]['blade_file'] ?? null)
            ->first();
    }
}
