<?php

namespace Collective\Html;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;

class HtmlServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Supported Blade Directives
     *
     * @var array
     */
    protected $directives = ['entities', 'decode', 'script', 'style', 'image', 'favicon', 'link', 'secureLink', 'linkAsset', 'linkSecureAsset', 'linkRoute', 'linkAction', 'mailto', 'email', 'ol', 'ul', 'dl', 'meta', 'tag', 'open', 'model', 'close', 'token', 'label', 'input', 'text', 'password', 'hidden', 'email', 'tel', 'number', 'date', 'datetime', 'datetimeLocal', 'time', 'url', 'file', 'textarea', 'select', 'selectRange', 'selectYear', 'selectMonth', 'getSelectOption', 'checkbox', 'radio', 'reset', 'image', 'color', 'submit', 'button', 'old',
    ];

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/html.php', 'html');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/html.php' => $this->app->configPath('html.php'),
            ], 'html-config');
        }

        $this->registerHtmlBuilder();

        $this->registerFormBuilder();

        $this->app->alias('html', HtmlBuilder::class);
        $this->app->alias('form', FormBuilder::class);

        $this->registerBladeDirectives();
    }

    /**
     * Register the HTML builder instance.
     */
    protected function registerHtmlBuilder(): void
    {
        $this->app->singleton('html', function ($app) {
            return new HtmlBuilder($app['view'], $app['url']);
        });
    }

    /**
     * Register the form builder instance.
     */
    protected function registerFormBuilder(): void
    {
        $this->app->singleton('form', function ($app) {
            $form = new FormBuilder($app['html'], $app['url'], $app['view'], $app['session.store']->token(), $app['request']);

            return $form->setSessionStore($app['session.store']);
        });
    }

    /**
     * Register Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        $this->app->afterResolving('blade.compiler', function (BladeCompiler $bladeCompiler) {
            $namespaces = [
                'Html' => get_class_methods(HtmlBuilder::class),
                'Form' => get_class_methods(FormBuilder::class),
            ];

            foreach ($namespaces as $namespace => $methods) {
                foreach ($methods as $method) {
                    if (in_array($method, $this->directives)) {
                        $snakeMethod = Str::snake($method);
                        $directive = strtolower($namespace).'_'.$snakeMethod;

                        $bladeCompiler->directive($directive, function ($expression) use ($namespace, $method) {
                            return "<?php echo $namespace::$method($expression); ?>";
                        });
                    }
                }
            }
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['html', 'form', HtmlBuilder::class, FormBuilder::class];
    }
}
