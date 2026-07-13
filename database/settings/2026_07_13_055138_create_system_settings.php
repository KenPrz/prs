<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // env() is consumed once here (VAT_RATE was a whole percent, e.g. 12);
        // after this migration the database owns the values.
        $this->migrator->add('finance.vat_rate', ((float) env('VAT_RATE', 12)) / 100);
        $this->migrator->add('finance.currency_symbol', (string) env('CURRENCY_SYMBOL', '₱'));
        $this->migrator->add('general.records_per_page', 10);
        $this->migrator->add('general.access_log_retention_days', (int) env('ACCESS_LOG_RETENTION_DAYS', 180));
    }
};
