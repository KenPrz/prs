@props([
    'formCode' => '',
])
<style>
    /*
     * Spatie's Dompdf driver merges this view into the HTML body (not Chrome's
     * margin footer). Without layout, the footer sits directly under the last
     * line of the PO, so short orders look like a band in the middle of the page.
     * Treat the page as at least A4 tall and pin the wrapper to the bottom.
     */
    body {
        display: flex;
        flex-direction: column;
        min-height: 297mm;
    }
    .pdf-footer {
        margin-top: auto;
        flex-shrink: 0;
        width: 100%;
        box-sizing: border-box;
    }

    .doc-footer {
        width: 100%;
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 7px;
        line-height: 1.3;
        color: #333;
        padding: 0 24px 10px; /* match main document padding */
    }
    .doc-footer table {
        width: 100%;
        border-collapse: collapse;
    }
    .doc-footer td {
        vertical-align: top;
        padding: 0 4px;
    }
</style>

<div class="doc-footer">
    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        <table>
            <tr>
                <td style="width:33%;">
                    20 BKLTS. (50 X 4) SN: 003501-004500<br />
                    BIR ATP NO. 05BAU20240000000955<br />
                    DATE OF ATP: 01-31-2024
                </td>
                <td style="width:34%; text-align:center;">
                    <strong>Forms International Enterprises Corporation</strong><br />
                    #25 Carmel Avenue, Project 6, Quezon City<br />
                    Tel. No.: 8365-8155 &nbsp; VAT REG. TIN: 002-013-045-00000<br />
                    Printer's Accreditation No. 036MP2023000000001<br />
                    Date of Accreditation: 12-05-2023 &nbsp; Date of Expiration: 12-04-2028
                </td>
                <td style="width:33%; text-align:right;">
                    <strong>"THIS DOCUMENT IS NOT VALID FOR CLAIMING INPUT TAXES."</strong><br /><br />
                    {{ $formCode }}
                </td>
            </tr>
        </table>
    @endif
</div>
