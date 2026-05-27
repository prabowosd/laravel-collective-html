<?php

namespace Collective\Html\Components;

use Collective\Html\FormBuilder;
use Illuminate\Support\HtmlString;

class FormCheckbox extends FormComponent
{
    public function __construct(
        FormBuilder $form,
        public string $name,
        public ?string $label = null,
        public mixed $value = 1,
        public ?bool $checked = null,
    ) {
        parent::__construct($form);
    }

    public function toHtml(): HtmlString
    {
        $options = $this->mergeClass($this->extraAttributes(), $this->form->cssClass('check_input', 'form-check-input'));
        $options = $this->form->withValidationClass($this->name, $options);

        $control = $this->form->checkbox($this->name, $this->value, $this->checked, $options);

        $label = $this->renderLabel($this->name, $this->label, 'check_label', 'form-check-label');

        $wrapper = $this->form->cssClass('check_wrapper', 'form-check');

        return new HtmlString(
            '<div class="'.$wrapper.'">'.$control.$label.$this->form->error($this->name).'</div>'
        );
    }
}
