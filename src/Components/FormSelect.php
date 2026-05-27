<?php

namespace Collective\Html\Components;

use Collective\Html\FormBuilder;
use Illuminate\Support\HtmlString;

class FormSelect extends FormComponent
{
    /**
     * @param  array<int|string, mixed>  $options
     * @param  class-string|null  $enum
     */
    public function __construct(
        FormBuilder $form,
        public string $name,
        public ?string $label = null,
        public array $options = [],
        public ?string $enum = null,
        public mixed $model = null,
        public string $labelColumn = 'name',
        public string $valueColumn = 'id',
        public mixed $selected = null,
    ) {
        parent::__construct($form);
    }

    public function toHtml(): HtmlString
    {
        $attributes = $this->mergeClass($this->extraAttributes(), $this->form->cssClass('select', 'form-select'));
        $attributes = $this->form->withValidationClass($this->name, $attributes);

        $control = match (true) {
            $this->enum !== null => $this->form->enumSelect($this->name, $this->enum, $this->selected, $attributes),
            $this->model !== null => $this->form->modelSelect($this->name, $this->model, $this->labelColumn, $this->valueColumn, $this->selected, $attributes),
            default => $this->form->select($this->name, $this->options, $this->selected, $attributes),
        };

        return new HtmlString(
            $this->renderLabel($this->name, $this->label).$control.$this->form->error($this->name)
        );
    }
}
