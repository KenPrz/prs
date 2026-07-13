<?php

/**
 * Regenerates the four default Carbone docx templates in the parent directory:
 * po-template.docx, pr-template.docx, prf-template.docx, rr-template.docx.
 *
 * Run: php database/seeders/data/documents/generator/generate-templates.php
 *
 * Placeholder keys must match the builders in app/Services/DocxTemplateProcessor.php.
 * Signature slots are transparent 3:1 placeholder PNGs renamed to
 * word/media/sig_{key}.png inside the zip — the exact paths
 * app/Services/DocxSignatureStamper.php overwrites at render time.
 */

error_reporting(E_ALL & ~E_DEPRECATED); // PHPWord 1.4 is noisy on PHP 8.5

require __DIR__.'/../../../../../vendor/autoload.php';

use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Media;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Image;

const OUT_DIR = __DIR__.'/..';
const BOLD = ['bold' => true];
const CENTER = ['alignment' => Jc::CENTER];
const HEADER_CELL = ['bgColor' => 'D9D9D9', 'valign' => 'center'];

// Word content widths in twips (page minus margins).
const LETTER_CONTENT = 9360;   // 12240 − 2×1440
const A4_CONTENT = 9638;       // 11906 − 2×1134

main();

function main(): void
{
    $pngs = placeholderPngs([
        'requested_by', 'checked_by', 'received_by', 'approved_by',
        'prepared_by', 'approvers', 'approver_1', 'approver_2', 'approver_3', 'approver_4',
    ]);

    buildPurchaseRequisition(OUT_DIR.'/pr-template.docx', $pngs);
    buildPurchaseOrder(OUT_DIR.'/po-template.docx', $pngs);
    buildReceivingReport(OUT_DIR.'/rr-template.docx', $pngs);
    buildPaymentRequestForm(OUT_DIR.'/prf-template.docx', $pngs);

    foreach (glob(OUT_DIR.'/*-template.docx') as $file) {
        printf("%-20s %6.1f KB\n", basename($file), filesize($file) / 1024);
    }
}

// -----------------------------------------------------------------------------
// Templates
// -----------------------------------------------------------------------------

function buildPurchaseRequisition(string $path, array $pngs): void
{
    [$word, $section] = newDoc('Purchase Requisition default template', 'A4');
    $section->addText('PURCHASE REQUISITION', ['bold' => true, 'size' => 14], titleStyle());

    $meta = grid($section, A4_CONTENT);
    $meta->addRow();
    field($meta->addCell(4819), 'PR No.', '{d.pr_number}');
    field($meta->addCell(4819), 'Date', '{d.pr_date}');
    $meta->addRow();
    field($meta->addCell(4819), 'Department', '{d.department}');
    field($meta->addCell(4819), 'Date Needed', '{d.date_needed}');

    spacer($section);

    $purpose = grid($section, A4_CONTENT);
    $purpose->addRow();
    $left = $purpose->addCell(4819);
    $left->addText('Purpose', BOLD);
    $left->addText(checkbox('purpose_type', 'CONSUMABLE', 'Consumable'));
    $left->addText(checkbox('purpose_type', 'NON_CONSUMABLE', 'Non-Consumable'));
    $left->addText(checkbox('purpose_type', 'OFFICE_USE', 'Office Use'));
    $left->addText(checkbox('purpose_type', 'PRODUCTION_USE', 'Production Use'));
    field($left, 'Expected Useful Life', '{d.expected_useful_life}');
    $right = $purpose->addCell(4819);
    $right->addText('Accounting Details', BOLD);
    $right->addText(checkbox('accounting_details', 'VAT_INCLUSIVE', 'VAT Inclusive'));
    $right->addText(checkbox('accounting_details', 'VAT_EXCLUSIVE', 'VAT Exclusive'));
    $right->addText(checkbox('accounting_details', 'NON_VAT', 'Non-VAT'));
    $right->addText(checkbox('accounting_details', 'ZERO_VAT', 'Zero-Rated VAT'));

    spacer($section);

    itemsTable($section, A4_CONTENT, [
        ['QTY', 800, 'qty'],
        ['UNIT', 800, 'unit'],
        ['DESCRIPTION', 2600, 'description'],
        ['DETAIL', 1638, 'descriptionDetail'],
        ['UNIT PRICE', 1300, 'unitPrice'],
        ['AMOUNT', 1300, 'amount'],
        ['REMARKS', 1200, 'remarks'],
    ]);

    spacer($section);

    // Signature insertion order must match $sigOrder below.
    $sigs = grid($section, A4_CONTENT);
    $sigs->addRow();
    sigCell($sigs->addCell(2410), 'Requested by', '{d.requested_by}', $pngs['requested_by']);
    sigCell($sigs->addCell(2410), 'Checked by', '{d.checked_by:convCRLF}', $pngs['checked_by']);
    sigCell($sigs->addCell(2410), 'Received by', '{d.received_by}', $pngs['received_by'], extra: 'Date: {d.received_date}');
    sigCell($sigs->addCell(2410), 'Approved by', '{d.approved_by:convCRLF}', $pngs['approved_by']);

    save($word, $path, ['requested_by', 'checked_by', 'received_by', 'approved_by']);
}

function buildPurchaseOrder(string $path, array $pngs): void
{
    [$word, $section] = newDoc('Purchase Order default template', 'Letter');
    $section->addText('PURCHASE ORDER', ['bold' => true, 'size' => 14], titleStyle());

    $meta = grid($section, LETTER_CONTENT);
    $meta->addRow();
    field($meta->addCell(4680), 'PO No.', '{d.po_number}');
    field($meta->addCell(4680), 'PO Date', '{d.po_date}');

    spacer($section);

    $parties = grid($section, LETTER_CONTENT);
    $parties->addRow();
    foreach (['Supplier', 'Bill To', 'Ship To'] as $h) {
        $parties->addCell(3120, HEADER_CELL)->addText($h, BOLD, CENTER);
    }
    $parties->addRow();
    $supplier = $parties->addCell(3120);
    $supplier->addText('{d.supplier_name}');
    $supplier->addText('{d.supplier_address:convCRLF}');
    field($supplier, 'Attn', '{d.supplier_contact}');
    $parties->addCell(3120)->addText('{d.bill_to_address:convCRLF}');
    $parties->addCell(3120)->addText('{d.ship_to_address:convCRLF}');

    spacer($section);

    $terms = grid($section, LETTER_CONTENT);
    $terms->addRow();
    field($terms->addCell(2340), 'Payment Terms', '{d.payment_terms}');
    field($terms->addCell(2340), 'Currency', '{d.currency}');
    field($terms->addCell(2340), 'PR No.', '{d.pr_number}');
    field($terms->addCell(2340), 'Price Type', '{d.price_type_label}');

    spacer($section);

    itemsTable($section, LETTER_CONTENT, [
        ['NO.', 600, 'no'],
        ['DESCRIPTION', 2960, 'description'],
        ['DELIVERY DATE', 1300, 'delivery_date'],
        ['UNIT', 800, 'unit'],
        ['QUANTITY', 1100, 'quantity'],
        ['UNIT PRICE', 1300, 'unit_price'],
        ['AMOUNT', 1300, 'total'],
    ]);

    spacer($section);

    $totals = grid($section, LETTER_CONTENT);
    foreach ([
        ['Net Amount', '{d.net_amount}'],
        ['VAT Rate', '{d.vat_rate}'],
        ['VAT Amount', '{d.vat_amount}'],
        ['Grand Total', '{d.grand_total}'],
    ] as [$label, $ph]) {
        $totals->addRow();
        $totals->addCell(6760)->addText($label, BOLD, ['alignment' => Jc::END]);
        $totals->addCell(2600)->addText($ph, null, ['alignment' => Jc::END]);
    }

    spacer($section);
    field($section, 'Remarks', '{d.remarks:convCRLF}');
    spacer($section);

    $sigs = grid($section, LETTER_CONTENT);
    $sigs->addRow();
    sigCell($sigs->addCell(4680), 'Prepared by', '{d.prepared_by}', $pngs['prepared_by']);
    sigCell($sigs->addCell(4680), 'Approved by', '{d.approvers_list}', $pngs['approvers'], wide: true);

    save($word, $path, ['prepared_by', 'approvers']);
}

function buildReceivingReport(string $path, array $pngs): void
{
    [$word, $section] = newDoc('Receiving Report default template', 'A4');
    $section->addText('RECEIVING REPORT', ['bold' => true, 'size' => 14], titleStyle());

    $meta = grid($section, A4_CONTENT);
    $meta->addRow();
    field($meta->addCell(4819), 'RR No.', '{d.rr_number}');
    field($meta->addCell(4819), 'Date Received', '{d.rr_date}');
    $meta->addRow();
    field($meta->addCell(4819), 'Supplier', '{d.supplier_name}');
    field($meta->addCell(4819), 'PO No.', '{d.po_number}');

    spacer($section);

    itemsTable($section, A4_CONTENT, [
        ['NO.', 600, 'no'],
        ['DESCRIPTION', 2938, 'description'],
        ['UNIT', 800, 'unit'],
        ['QTY ORDERED', 1100, 'ordered_qty'],
        ['QTY RECEIVED', 1100, 'received_qty'],
        ['QTY REJECTED', 1100, 'rejected_qty'],
        ['REMARKS', 2000, 'remarks'],
    ]);

    spacer($section);
    field($section, 'General Remarks', '{d.general_remarks:convCRLF}');
    spacer($section);

    $sigs = grid($section, A4_CONTENT);
    $sigs->addRow();
    sigCell($sigs->addCell(4819), 'Received by', '{d.received_by}', $pngs['received_by']);
    sigCell($sigs->addCell(4819), 'Approved by', '{d.approved_by}', $pngs['approved_by']);

    save($word, $path, ['received_by', 'approved_by']);
}

function buildPaymentRequestForm(string $path, array $pngs): void
{
    [$word, $section] = newDoc('Payment Request Form default template', 'A4');
    $section->addText('PAYMENT REQUEST FORM', ['bold' => true, 'size' => 14], titleStyle());

    $meta = grid($section, A4_CONTENT);
    $meta->addRow();
    field($meta->addCell(3212), 'PRF No.', '{d.prf_number}');
    field($meta->addCell(3212), 'Date', '{d.prf_date}');
    field($meta->addCell(3214), 'Department', '{d.department}');

    spacer($section);

    $fields = grid($section, A4_CONTENT);
    $fields->addRow();
    field($fields->addCell(9638, ['gridSpan' => 2]), 'Payee', '{d.payee_name}');
    $fields->addRow();
    field($fields->addCell(4819), 'Amount', '{d.amount}');
    field($fields->addCell(4819), 'Invoice No.', '{d.invoice_number}');
    $fields->addRow();
    field($fields->addCell(9638, ['gridSpan' => 2]), 'Description', '{d.purpose}');
    $fields->addRow();
    field($fields->addCell(9638, ['gridSpan' => 2]), 'Due Date', '{d.due_date}');

    spacer($section);

    $sigs = grid($section, A4_CONTENT);
    $sigs->addRow();
    sigCell($sigs->addCell(9638, ['gridSpan' => 4]), 'Prepared by', '{d.prepared_by}', $pngs['prepared_by']);
    $sigs->addRow();
    $sigs->addCell(9638, array_merge(HEADER_CELL, ['gridSpan' => 4]))->addText('Approved by', BOLD, CENTER);
    $sigs->addRow();
    for ($i = 1; $i <= 4; $i++) {
        sigCell($sigs->addCell(2410), '', "{d.approver_{$i}_name}", $pngs["approver_{$i}"]);
    }

    save($word, $path, ['prepared_by', 'approver_1', 'approver_2', 'approver_3', 'approver_4']);
}

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

/** @return array{0: PhpWord, 1: Section} */
function newDoc(string $title, string $paper): array
{
    Media::resetElements();

    $word = new PhpWord();
    $word->setDefaultFontName('Calibri');
    $word->setDefaultFontSize(9);
    $word->getDocInfo()->setCreator('OpenPRS');
    $word->getDocInfo()->setTitle($title);

    $margin = $paper === 'Letter' ? 1440 : 1134;
    $section = $word->addSection([
        'paperSize' => $paper,
        'marginTop' => $margin, 'marginBottom' => $margin,
        'marginLeft' => $margin, 'marginRight' => $margin,
    ]);

    return [$word, $section];
}

function titleStyle(): array
{
    return ['alignment' => Jc::CENTER, 'spaceAfter' => 240];
}

function grid(Section $section, int $width): \PhpOffice\PhpWord\Element\Table
{
    return $section->addTable([
        'borderSize' => 6,
        'borderColor' => '808080',
        'cellMargin' => 80,
        'unit' => TblWidth::TWIP,
        'width' => $width,
    ]);
}

function spacer(Section $section): void
{
    $section->addText('', null, ['spaceAfter' => 0]);
}

/** A "Label: {d.placeholder}" line; the placeholder stays a single run. */
function field(Section|Cell $container, string $label, string $placeholder): void
{
    $run = $container->addTextRun();
    $run->addText($label.': ', BOLD);
    $run->addText($placeholder);
}

/** Carbone checkbox line, e.g. {d.x:ifEQ('V'):show('☒'):elseShow('☐')} Label */
function checkbox(string $field, string $value, string $label): string
{
    return "{d.{$field}:ifEQ('{$value}'):show('☒'):elseShow('☐')} {$label}";
}

/**
 * Items table: header row + the two Carbone repeat marker rows.
 * Every column carries its placeholder in both the [i] and [i+1] row.
 *
 * @param array<array{0: string, 1: int, 2: string}> $cols [label, width, key]
 */
function itemsTable(Section $section, int $width, array $cols): void
{
    $table = grid($section, $width);
    $table->addRow();
    foreach ($cols as [$label, $w]) {
        $table->addCell($w, HEADER_CELL)->addText($label, BOLD, CENTER);
    }
    foreach (['i', 'i+1'] as $ix) {
        $table->addRow();
        foreach ($cols as [, $w, $key]) {
            $table->addCell($w)->addText("{d.items[{$ix}].{$key}}");
        }
    }
}

/** Caption, 3:1 signature placeholder image, then the name placeholder. */
function sigCell(Cell $cell, string $caption, string $namePlaceholder, string $png, bool $wide = false, ?string $extra = null): void
{
    if ($caption !== '') {
        $cell->addText($caption, BOLD);
    }
    $cell->addImage($png, [
        'width' => $wide ? 172.8 : 94.5,
        'height' => $wide ? 57.6 : 31.5,
        'unit' => Image::UNIT_PT,
        'alignment' => Jc::CENTER,
    ]);
    $cell->addText($namePlaceholder, null, CENTER);
    if ($extra !== null) {
        $cell->addText($extra, null, CENTER);
    }
}

/**
 * One transparent 300x100 PNG per signature key. Distinct file paths are
 * required: PHPWord dedupes media by source path, and each slot must be its
 * own media entry so the stamper can overwrite them independently.
 *
 * @return array<string, string> key => temp png path
 */
function placeholderPngs(array $keys): array
{
    $im = imagecreatetruecolor(300, 100);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));

    $paths = [];
    foreach ($keys as $key) {
        $paths[$key] = sys_get_temp_dir()."/carbone_sig_{$key}.png";
        imagepng($im, $paths[$key]);
    }

    return $paths;
}

/** Write the docx, then rename its media images to the sig_{key}.png slots. */
function save(PhpWord $word, string $path, array $sigOrder): void
{
    IOFactory::createWriter($word, 'Word2007')->save($path);

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("Cannot reopen {$path}");
    }

    // PHPWord names media entries with an insertion-order index; map that
    // order onto the signature keys.
    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('#^word/media/\D*(\d+)\.png$#', $name, $m)) {
            $entries[(int) $m[1]] = $name;
        }
    }
    ksort($entries);

    if (count($entries) !== count($sigOrder)) {
        throw new RuntimeException(sprintf(
            '%s: expected %d media images, found %d', basename($path), count($sigOrder), count($entries),
        ));
    }

    $rels = $zip->getFromName('word/_rels/document.xml.rels');
    foreach (array_values($entries) as $i => $old) {
        $new = "word/media/sig_{$sigOrder[$i]}.png";
        $zip->renameName($old, $new);
        $rels = str_replace(substr($old, strlen('word/')), substr($new, strlen('word/')), $rels);
    }
    $zip->addFromString('word/_rels/document.xml.rels', $rels);

    if (! $zip->close()) {
        throw new RuntimeException("Failed writing {$path}");
    }
}
