<?php

namespace App\Providers;

use App\Models\Booking;
use App\Observers\BookingObserver;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
       
        Booking::observe(BookingObserver::class);

        
        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

            $backendVerifyUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id'   => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            return $frontendUrl . '/auth/verify-email?verify_url=' . urlencode($backendVerifyUrl);
        });
    }
}
