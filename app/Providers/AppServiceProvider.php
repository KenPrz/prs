<?php

namespace App\Providers;

use App\Events\Workflow\WorkflowCompleted;
use App\Listeners\LogAuthEvent;
use App\Listeners\ProcurementNotificationSubscriber;
use App\Listeners\SnapshotPaymentRequestFormFinalDocument;
use App\Listeners\SnapshotReceivingReportFinalDocument;
use App\Listeners\WorkflowNotificationSubscriber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        Event::listen(WorkflowCompleted::class, SnapshotReceivingReportFinalDocument::class);
        Event::listen(WorkflowCompleted::class, SnapshotPaymentRequestFormFinalDocument::class);
        Event::subscribe(WorkflowNotificationSubscriber::class);
        Event::subscribe(ProcurementNotificationSubscriber::class);
        Event::subscribe(LogAuthEvent::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
