<?php

namespace CrCms\User\Providers;

use CrCms\Foundation\App\Providers\ModuleServiceProvider;
use CrCms\User\Events\AuthInfoEvent;
use CrCms\User\Listeners\AuthInfoListener;
use CrCms\User\Listeners\RegisterMailListener;
use CrCms\User\Listeners\Repositories\AuthLogListener;
use CrCms\User\Listeners\Repositories\UserBehaviorListener;
use CrCms\User\Listeners\Repositories\UserListener;
use CrCms\User\Repositories\AuthLogRepository;
use CrCms\User\Repositories\UserBehaviorRepository;
use CrCms\User\Repositories\UserRepository;
use CrCms\User\Services\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Tymon\JWTAuth\Providers\LaravelServiceProvider;

/**
 * Class ModuleServiceProvider
 * @package CrCms\User\Providers
 */
class UserServiceProvider extends ModuleServiceProvider
{
    /**
     * @var string
     */
    protected $basePath = __DIR__ . '/../../';

    /**
     * @var string
     */
    protected $name = 'user';

    /**
     * @return void
     */
    protected function repositoryListener(): void
    {
        UserRepository::observer(UserListener::class);
        AuthLogRepository::observer(AuthLogListener::class);
        UserBehaviorRepository::observer(UserBehaviorListener::class);
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        $this->publishes([
            $this->basePath . 'config/auth.php' => config_path('auth.php'),
            $this->basePath . 'config/config.php' => config_path("{$this->name}.php"),
            $this->basePath . 'resources/lang' => resource_path("lang/vendor/{$this->name}"),
        ]);

        $this->loadViewsFrom($this->basePath . '/resources/views', $this->name);

        Event::listen(Registered::class, RegisterMailListener::class);
        Event::listen(AuthInfoEvent::class, AuthInfoListener::class);
    }

    /**
     * @return void
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            $this->basePath . 'config/auth.php', 'auth'
        );

        $this->app->singleton(\CrCms\User\Services\Verification\Contracts\VerificationCode::class,
            \CrCms\User\Services\Verification\VerificationCode::class);

        $this->app->register(\Illuminate\Auth\AuthServiceProvider::class);
        $this->app->register(\Illuminate\Auth\Passwords\PasswordResetServiceProvider::class);

        $this->app->singleton('auth.password', function ($app) {
            return new PasswordBrokerManager($app);
        });

        $this->app->register(LaravelServiceProvider::class);
    }
}