<?php

namespace Collective\Html;

use Collective\Html\Components\FormCheckbox;
use Collective\Html\Components\FormInput;
use Collective\Html\Components\FormSelect;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;

class HtmlServiceProvider extends ServiceProvider
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

        $this->registerHtmlBuilder();

        $this->registerFormBuilder();

        $this->app->alias('html', HtmlBuilder::class);
        $this->app->alias('form', FormBuilder::class);
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/html.php' => $this->app->configPath('html.php'),
            ], 'html-config');
        }

        $this->registerBladeDirectives();

        $this->registerBladeComponents();
    }

    /**
     * Register the HTML builder instance.
     */
    protected function registerHtmlBuilder(): void
    {
        $this->app->singleton('html', fn ($app) => new HtmlBuilder($app['view'], $app['url']));
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

                        $bladeCompiler->directive($directive, fn ($expression) => "<?php echo $namespace::$method($expression); ?>");
                    }
                }
            }
        });
    }

    /**
     * Register the package's Blade components.
     */
    protected function registerBladeComponents(): void
    {
        $this->callAfterResolving('blade.compiler', function (BladeCompiler $bladeCompiler) {
            $bladeCompiler->component(FormInput::class, 'form-input');
            $bladeCompiler->component(FormSelect::class, 'form-select');
            $bladeCompiler->component(FormCheckbox::class, 'form-checkbox');
        });
    }
}
