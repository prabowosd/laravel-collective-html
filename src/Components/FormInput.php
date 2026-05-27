<?php

namespace Collective\Html\Components;

use Collective\Html\FormBuilder;
use Illuminate\Support\HtmlString;

class FormInput extends FormComponent
{
    public function __construct(
        FormBuilder $form,
        public string $name,
        public ?string $label = null,
        public string $type = 'text',
        public mixed $value = null,
    ) {
        parent::__construct($form);
    }

    public function toHtml(): HtmlString
    {
        $options = $this->mergeClass($this->extraAttributes(), $this->form->cssClass('control', 'form-control'));
        $options = $this->form->withValidationClass($this->name, $options);

        $control = $this->type === 'textarea'
            ? $this->form->textarea($this->name, $this->value, $options)
            : $this->form->input($this->type, $this->name, $this->value, $options);

        return new HtmlString(
            $this->renderLabel($this->name, $this->label).$control.$this->form->error($this->name)
        );
    }
}
