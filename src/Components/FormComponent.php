<?php

namespace Collective\Html\Components;

use Collective\Html\FormBuilder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

abstract class FormComponent extends Component
{
    public function __construct(protected FormBuilder $form) {}

    /**
     * Build the field's HTML.
     */
    abstract public function toHtml(): HtmlString;

    /**
     * Return a deferred Htmlable so the build runs during renderComponent(),
     * i.e. after Blade's pipeline has applied withAttributes(). Returning the
     * HtmlString directly would build it too early (before the attribute bag
     * is populated); returning a closure would re-compile the output as Blade.
     */
    public function render(): Htmlable
    {
        return new class($this) implements Htmlable
        {
            public function __construct(private FormComponent $component) {}

            public function toHtml(): string
            {
                return (string) $this->component->toHtml();
            }
        };
    }

    /**
     * Pass-through HTML attributes that were not mapped to constructor props.
     *
     * @return array<string, mixed>
     */
    protected function extraAttributes(): array
    {
        return isset($this->attributes) ? $this->attributes->getAttributes() : [];
    }

    /**
     * Prepend a CSS class to the options, skipping empty values so a blank
     * class attribute is never rendered.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function mergeClass(array $options, string $class): array
    {
        $class = trim($class.' '.($options['class'] ?? ''));

        if ($class !== '') {
            $options['class'] = $class;
        }

        return $options;
    }

    /**
     * Render the field label, or an empty string when none was provided.
     */
    protected function renderLabel(string $name, ?string $label, string $classKey = 'label', string $classDefault = 'form-label'): string
    {
        if ($label === null) {
            return '';
        }

        $options = $this->mergeClass([], $this->form->cssClass($classKey, $classDefault));

        return (string) $this->form->label($name, $label, $options);
    }
}
