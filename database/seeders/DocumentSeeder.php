<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\PaymentRequestForm;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /** @var array<string, array{resource_class: class-string, blade_file: string, template: string}> */
    private array $documents = [
        'po' => [
            'resource_class' => PurchaseOrder::class,
            'blade_file' => 'documents.purchase-order',
            'template' => 'po-template.docx',
        ],
        'pr' => [
            'resource_class' => PurchaseRequisition::class,
            'blade_file' => 'documents.purchase-requisition',
            'template' => 'pr-template.docx',
        ],
        'prf' => [
            'resource_class' => PaymentRequestForm::class,
            'blade_file' => 'documents.payment-request-form',
            'template' => 'prf-template.docx',
        ],
        'rr' => [
            'resource_class' => ReceivingReport::class,
            'blade_file' => 'documents.receiving-report',
            'template' => 'rr-template.docx',
        ],
    ];

    public function run(): void
    {
        foreach ($this->documents as $config) {
            $doc = Document::query()->updateOrCreate(
                [
                    'resource_class' => $config['resource_class'],
                    'blade_file' => $config['blade_file'],
                ],
                [],
            );

            $templatePath = database_path('seeders/data/documents/'.$config['template']);

            if (! file_exists($templatePath)) {
                continue;
            }

            // Sync: replace the bound template when the repo file changed, so
            // template updates roll out with a reseed. An admin-uploaded
            // custom template (different name) is left alone.
            $bound = $doc->getFirstMedia('docx_template');
            $sourceHash = md5_file($templatePath);
            $boundHash = $bound && is_readable($bound->getPath()) ? md5_file($bound->getPath()) : null;

            if ($bound === null || ($bound->file_name === $config['template'] && $boundHash !== $sourceHash)) {
                $doc->clearMediaCollection('docx_template');
                $doc->addMedia($templatePath)
                    ->preservingOriginal()
                    ->toMediaCollection('docx_template');
            }
        }
    }
}
