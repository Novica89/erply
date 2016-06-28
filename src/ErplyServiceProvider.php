<?php namespace Novica89\Erply;

use Illuminate\Support\ServiceProvider;

class ErplyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/erply.php' => config_path('erply.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Bind the instance of a class to a Facade
        $this->app->singleton('novicaErply', function ($app) {
            return new Erply();
        });
    }
}
