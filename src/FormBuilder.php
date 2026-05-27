<?php

namespace Collective\Html;

use BadMethodCallException;
use Carbon\Carbon;
use DateTime;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\ViewErrorBag;

class FormBuilder
{
    use Componentable, Macroable {
        Macroable::__call as macroCall;
        Componentable::__call as componentCall;
    }

    /**
     * Consider Request variables while auto fill.
     *
     * @var bool
     */
    protected $considerRequest = false;

    /**
     * The session store implementation.
     *
     * @var Session
     */
    protected $session;

    /**
     * The current model instance for the form.
     *
     * @var mixed
     */
    protected $model;

    /**
     * An array of label names we've created.
     *
     * @var array
     */
    protected $labels = [];

    /**
     * The reserved form open attributes.
     *
     * @var array
     */
    protected $reserved = ['method', 'url', 'route', 'action', 'files'];

    /**
     * The form methods that should be spoofed, in uppercase.
     *
     * @var array
     */
    protected $spoofedMethods = ['DELETE', 'PATCH', 'PUT'];

    /**
     * The types of inputs to not fill values on by default.
     *
     * @var array
     */
    protected $skipValueTypes = ['file', 'password', 'checkbox', 'radio'];

    /**
     * Input Type.
     *
     * @var null
     */
    protected $type = null;

    /**
     * Create a new form builder instance.
     *
     * @param  string  $csrfToken
     */
    public function __construct(
        protected HtmlBuilder $html,
        protected UrlGenerator $url,
        protected Factory $view,
        protected $csrfToken,
        protected ?Request $request = null,
    ) {}

    /**
     * Open up a new HTML form.
     */
    public function open(array $options = []): HtmlString
    {
        $method = Arr::get($options, 'method', 'post');

        // We need to extract the proper method from the attributes. If the method is
        // something other than GET or POST we'll use POST since we will spoof the
        // actual method since forms don't support the reserved methods in HTML.
        $attributes['method'] = $this->getMethod($method);

        $attributes['action'] = $this->getAction($options);

        $attributes['accept-charset'] = 'UTF-8';

        // If the method is PUT, PATCH or DELETE we will need to add a spoofer hidden
        // field that will instruct the Symfony request to pretend the method is a
        // different method than it actually is, for convenience from the forms.
        $append = $this->getAppendage($method);

        if (isset($options['files']) && $options['files']) {
            $options['enctype'] = 'multipart/form-data';
        }

        // Finally we're ready to create the final form HTML field. We will attribute
        // format the array of attributes. We will also add on the appendage which
        // is used to spoof requests for this PUT, PATCH, etc. methods on forms.
        $attributes = array_merge(
            $attributes,
            Arr::except($options, $this->reserved)
        );

        // Finally, we will concatenate all of the attributes into a single string so
        // we can build out the final form open statement. We'll also append on an
        // extra value for the hidden _method field if it's needed for the form.
        $attributes = $this->html->attributes($attributes);

        return $this->toHtmlString('<form'.$attributes.'>'.$append);
    }

    /**
     * Create a new model based form builder.
     *
     * @param  mixed  $model
     */
    public function model($model, array $options = []): HtmlString
    {
        $this->model = $model;

        return $this->open($options);
    }

    /**
     * Set the model instance on the form builder.
     *
     * @param  mixed  $model
     */
    public function setModel($model): void
    {
        $this->model = $model;
    }

    /**
     * Get the current model instance on the form builder.
     *
     * @return mixed $model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Close the current form.
     *
     * @return string
     */
    public function close(): HtmlString
    {
        $this->labels = [];

        $this->model = null;

        return $this->toHtmlString('</form>');
    }

    /**
     * Generate a hidden field with the current CSRF token.
     *
     * @return string
     */
    public function token(): HtmlString
    {
        $token = ! empty($this->csrfToken) ? $this->csrfToken : $this->session->token();

        return $this->hidden('_token', $token);
    }

    /**
     * Create a form label element.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     * @param  bool  $escape_html
     */
    public function label($name, $value = null, $options = [], $escape_html = true): HtmlString
    {
        $this->labels[] = $name;

        $options = $this->html->attributes($options);

        $value = $this->formatLabel($name, $value);

        if ($escape_html) {
            $value = $this->html->entities($value);
        }

        return $this->toHtmlString('<label for="'.$name.'"'.$options.'>'.$value.'</label>');
    }

    /**
     * Format the label value.
     *
     * @param  string  $name
     * @param  string|null  $value
     * @return string
     */
    protected function formatLabel($name, $value)
    {
        return $value ?: ucwords(str_replace('_', ' ', $name));
    }

    /**
     * Create a form input field.
     *
     * @param  string  $type
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function input($type, $name, $value = null, $options = []): HtmlString
    {
        $this->type = $type;

        if (! isset($options['name'])) {
            $options['name'] = $name;
        }

        // We will get the appropriate value for the given field. We will look for the
        // value in the session for the value in the old input data then we'll look
        // in the model instance if one is set. Otherwise we will just use empty.
        $id = $this->getIdAttribute($name, $options);

        if (! in_array($type, $this->skipValueTypes)) {
            $value = $this->getValueAttribute($name, $value);
        }

        // Once we have the type, value, and ID we can merge them into the rest of the
        // attributes array so we can convert them into their HTML attribute format
        // when creating the HTML element. Then, we will return the entire input.
        $merge = compact('type', 'value', 'id');

        $options = array_merge($options, $merge);

        return $this->toHtmlString('<input'.$this->html->attributes($options).'>');
    }

    /**
     * Create a text input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function text($name, $value = null, $options = []): HtmlString
    {
        return $this->input('text', $name, $value, $options);
    }

    /**
     * Create a password input field.
     *
     * @param  string  $name
     * @param  array  $options
     */
    public function password($name, $options = []): HtmlString
    {
        return $this->input('password', $name, '', $options);
    }

    /**
     * Create a range input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function range($name, $value = null, $options = []): HtmlString
    {
        return $this->input('range', $name, $value, $options);
    }

    /**
     * Create a hidden input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function hidden($name, $value = null, $options = []): HtmlString
    {
        return $this->input('hidden', $name, $value, $options);
    }

    /**
     * Create a search input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function search($name, $value = null, $options = []): HtmlString
    {
        return $this->input('search', $name, $value, $options);
    }

    /**
     * Create an e-mail input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function email($name, $value = null, $options = []): HtmlString
    {
        return $this->input('email', $name, $value, $options);
    }

    /**
     * Create a tel input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function tel($name, $value = null, $options = []): HtmlString
    {
        return $this->input('tel', $name, $value, $options);
    }

    /**
     * Create a number input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function number($name, $value = null, $options = []): HtmlString
    {
        return $this->input('number', $name, $value, $options);
    }

    /**
     * Create a date input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function date($name, $value = null, $options = []): HtmlString
    {
        $value ??= $this->getValueAttribute($name, $value);

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        return $this->input('date', $name, $value, $options);
    }

    /**
     * Create a datetime input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function datetime($name, $value = null, $options = []): HtmlString
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format(DateTime::RFC3339);
        }

        return $this->input('datetime', $name, $value, $options);
    }

    /**
     * Create a datetime-local input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function datetimeLocal($name, $value = null, $options = []): HtmlString
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d\TH:i');
        }

        return $this->input('datetime-local', $name, $value, $options);
    }

    /**
     * Create a time input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function time($name, $value = null, $options = []): HtmlString
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('H:i');
        }

        return $this->input('time', $name, $value, $options);
    }

    /**
     * Create a url input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function url($name, $value = null, $options = []): HtmlString
    {
        return $this->input('url', $name, $value, $options);
    }

    /**
     * Create a week input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function week($name, $value = null, $options = []): HtmlString
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-\WW');
        }

        return $this->input('week', $name, $value, $options);
    }

    /**
     * Create a file input field.
     *
     * @param  string  $name
     * @param  array  $options
     */
    public function file($name, $options = []): HtmlString
    {
        return $this->input('file', $name, null, $options);
    }

    /**
     * Create a textarea input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function textarea($name, $value = null, $options = []): HtmlString
    {
        $this->type = 'textarea';

        if (! isset($options['name'])) {
            $options['name'] = $name;
        }

        // Next we will look for the rows and cols attributes, as each of these are put
        // on the textarea element definition. If they are not present, we will just
        // assume some sane default values for these attributes for the developer.
        $options = $this->setTextAreaSize($options);

        $options['id'] = $this->getIdAttribute($name, $options);

        $value = (string) $this->getValueAttribute($name, $value);

        unset($options['size']);

        // Next we will convert the attributes into a string form. Also we have removed
        // the size attribute, as it was merely a short-cut for the rows and cols on
        // the element. Then we'll create the final textarea elements HTML for us.
        $options = $this->html->attributes($options);

        return $this->toHtmlString('<textarea'.$options.'>'.e($value, false).'</textarea>');
    }

    /**
     * Set the text area size on the attributes.
     *
     * @param  array  $options
     */
    protected function setTextAreaSize($options): array
    {
        if (isset($options['size'])) {
            return $this->setQuickTextAreaSize($options);
        }

        // If the "size" attribute was not specified, we will just look for the regular
        // columns and rows attributes, using sane defaults if these do not exist on
        // the attributes array. We'll then return this entire options array back.
        $cols = Arr::get($options, 'cols', 50);

        $rows = Arr::get($options, 'rows', 10);

        return array_merge($options, compact('cols', 'rows'));
    }

    /**
     * Set the text area size using the quick "size" attribute.
     *
     * @param  array  $options
     */
    protected function setQuickTextAreaSize($options): array
    {
        $segments = explode('x', $options['size']);

        return array_merge($options, ['cols' => $segments[0], 'rows' => $segments[1]]);
    }

    /**
     * Create a select box field.
     *
     * @param  string  $name
     * @param  array  $list
     * @param  string|bool  $selected
     */
    public function select(
        $name,
        $list = [],
        $selected = null,
        array $selectAttributes = [],
        array $optionsAttributes = [],
        array $optgroupsAttributes = []
    ): HtmlString {
        $this->type = 'select';

        // When building a select box the "value" attribute is really the selected one
        // so we will use that when checking the model or session for a value which
        // should provide a convenient method of re-populating the forms on post.
        $selected = $this->getValueAttribute($name, $selected);

        $selectAttributes['id'] = $this->getIdAttribute($name, $selectAttributes);

        if (! isset($selectAttributes['name'])) {
            $selectAttributes['name'] = $name;
        }

        // We will simply loop through the options and build an HTML value for each of
        // them until we have an array of HTML declarations. Then we will join them
        // all together into one single HTML element that can be put on the form.
        $html = [];

        if (isset($selectAttributes['placeholder'])) {
            $html[] = $this->placeholderOption($selectAttributes['placeholder'], $selected);
            unset($selectAttributes['placeholder']);
        }

        foreach ($list as $value => $display) {
            $optionAttributes = $optionsAttributes[$value] ?? [];
            $optgroupAttributes = $optgroupsAttributes[$value] ?? [];
            $html[] = $this->getSelectOption($display, $value, $selected, $optionAttributes, $optgroupAttributes);
        }

        // Once we have all of this HTML, we can join this into a single element after
        // formatting the attributes into an HTML "attributes" string, then we will
        // build out a final select statement, which will contain all the values.
        $selectAttributes = $this->html->attributes($selectAttributes);

        $list = implode('', $html);

        return $this->toHtmlString("<select{$selectAttributes}>{$list}</select>");
    }

    /**
     * Create a select range field.
     *
     * @param  string  $name
     * @param  string  $begin
     * @param  string  $end
     * @param  string  $selected
     * @param  array  $options
     */
    public function selectRange($name, $begin, $end, $selected = null, $options = []): HtmlString
    {
        $range = array_combine($range = range($begin, $end), $range);

        return $this->select($name, $range, $selected, $options);
    }

    /**
     * Create a select year field.
     *
     * @param  string  $name
     * @param  string  $begin
     * @param  string  $end
     * @param  string  $selected
     * @param  array  $options
     * @return mixed
     */
    public function selectYear(): HtmlString
    {
        return call_user_func_array([$this, 'selectRange'], func_get_args());
    }

    /**
     * Create a select month field.
     *
     * @param  string  $name
     * @param  string  $selected
     * @param  array  $options
     * @param  string  $format
     */
    public function selectMonth($name, $selected = null, $options = [], $format = '%B'): HtmlString
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[$month] = Carbon::create()->day(1)->month($month)->translatedFormat('F');
        }

        return $this->select($name, $months, $selected, $options);
    }

    /**
     * Create a select box populated from a PHP enum's cases.
     *
     * Backed enums use their value as the option value; pure enums use the
     * case name. Labels come from a `label()` method on the enum when present,
     * otherwise the case name is humanized.
     *
     * @param  class-string  $enum
     * @param  string|bool  $selected
     */
    public function enumSelect($name, string $enum, $selected = null, array $selectAttributes = [], array $optionsAttributes = []): HtmlString
    {
        return $this->select($name, $this->enumToList($enum), $selected, $selectAttributes, $optionsAttributes);
    }

    /**
     * Build a value => label list from an enum class.
     *
     * @param  class-string  $enum
     * @return array<int|string, string>
     */
    protected function enumToList(string $enum): array
    {
        $list = [];

        foreach ($enum::cases() as $case) {
            $value = $case instanceof \BackedEnum ? $case->value : $case->name;

            $list[$value] = method_exists($case, 'label') ? $case->label() : Str::headline($case->name);
        }

        return $list;
    }

    /**
     * Create a select box populated from an Eloquent source.
     *
     * The source may be a model class string, a query/relation, an Eloquent
     * collection, a base collection, or a plain array of rows.
     *
     * @param  class-string|iterable|object  $source
     * @param  string|bool  $selected
     */
    public function modelSelect($name, $source, $labelColumn = 'name', $valueColumn = 'id', $selected = null, array $selectAttributes = [], array $optionsAttributes = []): HtmlString
    {
        return $this->select($name, $this->modelToList($source, $labelColumn, $valueColumn), $selected, $selectAttributes, $optionsAttributes);
    }

    /**
     * Resolve a model source into a value => label list.
     *
     * @param  class-string|iterable|object  $source
     * @return array<int|string, mixed>
     */
    protected function modelToList($source, $labelColumn, $valueColumn): array
    {
        if (is_string($source) && class_exists($source)) {
            $source = $source::query()->get();
        }

        if (is_object($source) && ! $source instanceof Collection && method_exists($source, 'get')) {
            $source = $source->get();
        }

        return collect($source)->pluck($labelColumn, $valueColumn)->all();
    }

    /**
     * Get the select option for the given value.
     *
     * @param  string  $display
     * @param  string  $value
     * @param  string  $selected
     */
    public function getSelectOption($display, $value, $selected, array $attributes = [], array $optgroupAttributes = []): HtmlString
    {
        if (is_iterable($display)) {
            return $this->optionGroup($display, $value, $selected, $optgroupAttributes, $attributes);
        }

        return $this->option($display, $value, $selected, $attributes);
    }

    /**
     * Create an option group form element.
     *
     * @param  array  $list
     * @param  string  $label
     * @param  string  $selected
     * @param  int  $level
     */
    protected function optionGroup($list, $label, $selected, array $attributes = [], array $optionsAttributes = [], $level = 0): HtmlString
    {
        $html = [];
        $space = str_repeat('&nbsp;', $level);
        foreach ($list as $value => $display) {
            $optionAttributes = $optionsAttributes[$value] ?? [];
            if (is_iterable($display)) {
                $html[] = $this->optionGroup($display, $value, $selected, $attributes, $optionAttributes, $level + 5);
            } else {
                $html[] = $this->option($space.$display, $value, $selected, $optionAttributes);
            }
        }

        return $this->toHtmlString('<optgroup label="'.e($space.$label, false).'"'.$this->html->attributes($attributes).'>'.implode('', $html).'</optgroup>');
    }

    /**
     * Create a select element option.
     *
     * @param  string  $display
     * @param  string  $value
     * @param  string  $selected
     */
    protected function option($display, $value, $selected, array $attributes = []): HtmlString
    {
        $selected = $this->getSelectedValue($value, $selected);

        $options = array_merge(['value' => $value, 'selected' => $selected], $attributes);

        $string = '<option'.$this->html->attributes($options).'>';
        if ($display !== null) {
            $string .= e($display, false).'</option>';
        }

        return $this->toHtmlString($string);
    }

    /**
     * Create a placeholder select element option.
     */
    protected function placeholderOption($display, $selected): HtmlString
    {
        $selected = $this->getSelectedValue(null, $selected);

        $options = [
            'selected' => $selected,
            'value' => '',
            'hidden' => 'true',
        ];

        return $this->toHtmlString('<option'.$this->html->attributes($options).'>'.e($display, false).'</option>');
    }

    /**
     * Determine if the value is selected.
     *
     * @param  string  $value
     * @param  string  $selected
     * @return null|string
     */
    protected function getSelectedValue($value, $selected)
    {
        if (is_array($selected)) {
            return in_array($value, $selected, true) || in_array((string) $value, $selected, true) ? 'selected' : null;
        } elseif ($selected instanceof Collection) {
            return $selected->contains($value) ? 'selected' : null;
        }
        if (is_int($value) && is_bool($selected)) {
            return (bool) $value === $selected;
        }

        return ((string) $value === (string) $selected) ? 'selected' : null;
    }

    /**
     * Create a checkbox input field.
     *
     * @param  string  $name
     * @param  mixed  $value
     * @param  bool  $checked
     * @param  array  $options
     */
    public function checkbox($name, $value = 1, $checked = null, $options = []): HtmlString
    {
        return $this->checkable('checkbox', $name, $value, $checked, $options);
    }

    /**
     * Create a radio button input field.
     *
     * @param  string  $name
     * @param  mixed  $value
     * @param  bool  $checked
     * @param  array  $options
     */
    public function radio($name, $value = null, $checked = null, $options = []): HtmlString
    {
        if (is_null($value)) {
            $value = $name;
        }

        return $this->checkable('radio', $name, $value, $checked, $options);
    }

    /**
     * Create a checkable input field.
     *
     * @param  string  $type
     * @param  string  $name
     * @param  mixed  $value
     * @param  bool  $checked
     * @param  array  $options
     */
    protected function checkable($type, $name, $value, $checked, $options): HtmlString
    {
        $this->type = $type;

        $checked = $this->getCheckedState($type, $name, $value, $checked);

        if ($checked) {
            $options['checked'] = 'checked';
        }

        return $this->input($type, $name, $value, $options);
    }

    /**
     * Get the check state for a checkable input.
     *
     * @param  string  $type
     * @param  string  $name
     * @param  mixed  $value
     * @param  bool  $checked
     * @return bool
     */
    protected function getCheckedState($type, $name, $value, $checked)
    {
        switch ($type) {
            case 'checkbox':
                return $this->getCheckboxCheckedState($name, $value, $checked);

            case 'radio':
                return $this->getRadioCheckedState($name, $value, $checked);

            default:
                return $this->compareValues($name, $value);
        }
    }

    /**
     * Get the check state for a checkbox input.
     *
     * @param  string  $name
     * @param  mixed  $value
     * @param  bool  $checked
     * @return bool
     */
    protected function getCheckboxCheckedState($name, $value, $checked)
    {
        $request = $this->request($name);

        if (isset($this->session) && ! $this->oldInputIsEmpty() && is_null($this->old($name)) && ! $request) {
            return false;
        }

        if ($this->missingOldAndModel($name) && is_null($request)) {
            return $checked;
        }

        $posted = $this->getValueAttribute($name, $checked);

        if (is_array($posted)) {
            return in_array($value, $posted);
        } elseif ($posted instanceof Collection) {
            return $posted->contains('id', $value);
        } else {
            return (bool) $posted;
        }
    }

    /**
     * Get the check state for a radio input.
     *
     * @param  string  $name
     * @param  mixed  $value
     * @param  bool  $checked
     * @return bool
     */
    protected function getRadioCheckedState($name, $value, $checked)
    {
        $request = $this->request($name);

        if ($this->missingOldAndModel($name) && ! $request) {
            return $checked;
        }

        return $this->compareValues($name, $value);
    }

    /**
     * Determine if the provide value loosely compares to the value assigned to the field.
     * Use loose comparison because Laravel model casting may be in affect and therefore
     * 1 == true and 0 == false.
     *
     * @param  string  $name
     * @param  string  $value
     */
    protected function compareValues($name, $value): bool
    {
        return $this->getValueAttribute($name) == $value;
    }

    /**
     * Determine if old input or model input exists for a key.
     *
     * @param  string  $name
     */
    protected function missingOldAndModel($name): bool
    {
        return is_null($this->old($name)) && is_null($this->getModelValueAttribute($name));
    }

    /**
     * Create a HTML reset input element.
     *
     * @param  string  $value
     * @param  array  $attributes
     */
    public function reset($value, $attributes = []): HtmlString
    {
        return $this->input('reset', null, $value, $attributes);
    }

    /**
     * Create a HTML image input element.
     *
     * @param  string  $url
     * @param  string  $name
     * @param  array  $attributes
     */
    public function image($url, $name = null, $attributes = []): HtmlString
    {
        $attributes['src'] = $this->url->asset($url);

        return $this->input('image', $name, null, $attributes);
    }

    /**
     * Create a month input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function month($name, $value = null, $options = []): HtmlString
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m');
        }

        return $this->input('month', $name, $value, $options);
    }

    /**
     * Create a color input field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array  $options
     */
    public function color($name, $value = null, $options = []): HtmlString
    {
        return $this->input('color', $name, $value, $options);
    }

    /**
     * Create a submit button element.
     *
     * @param  string  $value
     * @param  array  $options
     */
    public function submit($value = null, $options = []): HtmlString
    {
        return $this->input('submit', null, $value, $options);
    }

    /**
     * Create a button element.
     *
     * @param  string  $value
     * @param  array  $options
     */
    public function button($value = null, $options = []): HtmlString
    {
        if (! array_key_exists('type', $options)) {
            $options['type'] = 'button';
        }

        return $this->toHtmlString('<button'.$this->html->attributes($options).'>'.$value.'</button>');
    }

    /**
     * Create a datalist box field.
     *
     * @param  string  $id
     * @param  array  $list
     */
    public function datalist($id, $list = []): HtmlString
    {
        $this->type = 'datalist';

        $attributes['id'] = $id;

        $html = [];

        if ($this->isAssociativeArray($list)) {
            foreach ($list as $value => $display) {
                $html[] = $this->option($display, $value, null, []);
            }
        } else {
            foreach ($list as $value) {
                $html[] = $this->option($value, $value, null, []);
            }
        }

        $attributes = $this->html->attributes($attributes);

        $list = implode('', $html);

        return $this->toHtmlString("<datalist{$attributes}>{$list}</datalist>");
    }

    /**
     * Determine if an array is associative.
     *
     * @param  array  $array
     */
    protected function isAssociativeArray($array): bool
    {
        return array_values($array) !== $array;
    }

    /**
     * Parse the form action method.
     *
     * @param  string  $method
     */
    protected function getMethod($method): string
    {
        $method = strtoupper($method);

        return $method !== 'GET' ? 'POST' : $method;
    }

    /**
     * Get the form action from the options.
     */
    protected function getAction(array $options): string
    {
        // We will also check for a "route" or "action" parameter on the array so that
        // developers can easily specify a route or controller action when creating
        // a form providing a convenient interface for creating the form actions.
        if (isset($options['url'])) {
            return $this->getUrlAction($options['url']);
        }

        if (isset($options['route'])) {
            return $this->getRouteAction($options['route']);
        }

        // If an action is available, we are attempting to open a form to a controller
        // action route. So, we will use the URL generator to get the path to these
        // actions and return them from the method. Otherwise, we'll use current.
        elseif (isset($options['action'])) {
            return $this->getControllerAction($options['action']);
        }

        return $this->url->current();
    }

    /**
     * Get the action for a "url" option.
     *
     * @param  array|string  $options
     */
    protected function getUrlAction($options): string
    {
        if (is_array($options)) {
            return $this->url->to($options[0], array_slice($options, 1));
        }

        return $this->url->to($options);
    }

    /**
     * Get the action for a "route" option.
     *
     * @param  array|string  $options
     */
    protected function getRouteAction($options): string
    {
        if (is_array($options)) {
            $parameters = array_slice($options, 1);

            if (array_keys($options) === [0, 1]) {
                $parameters = head($parameters);
            }

            return $this->url->route($options[0], $parameters);
        }

        return $this->url->route($options);
    }

    /**
     * Get the action for an "action" option.
     *
     * @param  array|string  $options
     */
    protected function getControllerAction($options): string
    {
        if (is_array($options)) {
            return $this->url->action($options[0], array_slice($options, 1));
        }

        return $this->url->action($options);
    }

    /**
     * Get the form appendage for the given method.
     *
     * @param  string  $method
     */
    protected function getAppendage($method): string
    {
        [$method, $appendage] = [strtoupper($method), ''];

        // If the HTTP method is in this list of spoofed methods, we will attach the
        // method spoofer hidden input to the form. This allows us to use regular
        // form to initiate PUT and DELETE requests in addition to the typical.
        if (in_array($method, $this->spoofedMethods)) {
            $appendage .= $this->hidden('_method', $method);
        }

        // If the method is something other than GET we will go ahead and attach the
        // CSRF token to the form, as this can't hurt and is convenient to simply
        // always have available on every form the developers creates for them.
        if ($method !== 'GET') {
            $appendage .= $this->token();
        }

        return $appendage;
    }

    /**
     * Get the ID attribute for a field name.
     *
     * @param  string  $name
     * @param  array  $attributes
     * @return string
     */
    public function getIdAttribute($name, $attributes)
    {
        if (array_key_exists('id', $attributes)) {
            return $attributes['id'];
        }

        if (in_array($name, $this->labels)) {
            return $name;
        }
    }

    /**
     * Get the value that should be assigned to the field.
     *
     * @param  string  $name
     * @param  string  $value
     * @return mixed
     */
    public function getValueAttribute($name, $value = null)
    {
        if (is_null($name)) {
            return $value;
        }

        $old = $this->old($name);

        if (! is_null($old) && $name !== '_method') {
            return $old;
        }

        if (function_exists('app')) {
            $hasNullMiddleware = app("Illuminate\Contracts\Http\Kernel")
                ->hasMiddleware(ConvertEmptyStringsToNull::class);

            if ($hasNullMiddleware
                && is_null($old)
                && is_null($value)
                && ! is_null($this->view->shared('errors'))
                && count(is_countable($this->view->shared('errors')) ? $this->view->shared('errors') : []) > 0
            ) {
                return null;
            }
        }

        $request = $this->request($name);
        if (! is_null($request) && $name != '_method') {
            return $request;
        }

        if (! is_null($value)) {
            return $value;
        }

        if (isset($this->model)) {
            return $this->getModelValueAttribute($name);
        }
    }

    /**
     * Take Request in fill process
     *
     * @param  bool  $consider
     */
    public function considerRequest($consider = true): void
    {
        $this->considerRequest = $consider;
    }

    /**
     * Get value from current Request
     *
     * @return array|null|string
     */
    protected function request($name)
    {
        if (! $this->considerRequest) {
            return null;
        }

        if (! isset($this->request)) {
            return null;
        }

        return $this->request->input($this->transformKey($name));
    }

    /**
     * Get the model value that should be assigned to the field.
     *
     * @param  string  $name
     * @return mixed
     */
    protected function getModelValueAttribute($name)
    {
        $key = $this->transformKey($name);

        if ((is_string($this->model) || is_object($this->model)) && method_exists($this->model, 'getFormValue')) {
            return $this->model->getFormValue($key);
        }

        $data = data_get($this->model, $key);
        if ($data instanceof \UnitEnum) {
            $data = $data->value;
        }

        return $data;
    }

    /**
     * Get a value from the session's old input.
     *
     * @param  string  $name
     * @return mixed
     */
    public function old($name)
    {
        if (isset($this->session)) {
            $key = $this->transformKey($name);
            $payload = $this->session->getOldInput($key);

            if (! is_array($payload)) {
                return $payload;
            }

            if (! in_array($this->type, ['select', 'checkbox'])) {
                if (! isset($this->payload[$key])) {
                    $this->payload[$key] = collect($payload);
                }

                if (! empty($this->payload[$key])) {
                    $value = $this->payload[$key]->shift();

                    return $value;
                }
            }

            return $payload;
        }
    }

    /**
     * Determine if the old input is empty.
     */
    public function oldInputIsEmpty(): bool
    {
        return isset($this->session) && count((array) $this->session->getOldInput()) === 0;
    }

    /**
     * Get the validation errors shared with the current view, if any.
     *
     * @return MessageBag|ViewErrorBag|null
     */
    protected function errors()
    {
        $errors = $this->view->shared('errors');

        return is_null($errors) ? null : $errors;
    }

    /**
     * Determine whether the given field has a validation error.
     */
    public function hasError($name): bool
    {
        $errors = $this->errors();

        return $errors !== null && $errors->has($this->transformKey($name));
    }

    /**
     * Get the first validation error message for the given field.
     */
    public function getError($name): ?string
    {
        if (! $this->hasError($name)) {
            return null;
        }

        return $this->errors()->first($this->transformKey($name));
    }

    /**
     * Render the validation feedback element for a field.
     *
     * Returns an empty HtmlString when the field has no error, so it is safe
     * to drop after any control unconditionally.
     */
    public function error($name, array $attributes = []): HtmlString
    {
        if (! $this->hasError($name)) {
            return $this->toHtmlString('');
        }

        $attributes['class'] = trim(($attributes['class'] ?? '').' '.$this->cssClass('feedback', 'invalid-feedback'));

        return $this->toHtmlString('<div'.$this->html->attributes($attributes).'>'.e($this->getError($name), false).'</div>');
    }

    /**
     * Merge the configured "invalid" class into the given field options when
     * the field currently has a validation error. Existing options pass
     * through untouched, keeping `Form::` calls backward compatible.
     */
    public function withValidationClass($name, array $options): array
    {
        if (! $this->hasError($name)) {
            return $options;
        }

        $options['class'] = trim(($options['class'] ?? '').' '.$this->cssClass('invalid', 'is-invalid'));

        return $options;
    }

    /**
     * Resolve a configured CSS class, falling back to the given default when
     * no config repository is bound (e.g. outside a full application).
     */
    public function cssClass(string $key, string $default = ''): string
    {
        $container = Container::getInstance();

        if ($container && $container->bound('config')) {
            return $container->make('config')->get("html.classes.{$key}", $default);
        }

        return $default;
    }

    /**
     * Transform key from array to dot syntax.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function transformKey($key): string
    {
        return str_replace(['.', '[]', '[', ']'], ['_', '', '.', ''], $key);
    }

    /**
     * Transform the string to an Html serializable object
     */
    protected function toHtmlString($html): HtmlString
    {
        return new HtmlString($html);
    }

    /**
     * Get the session store implementation.
     *
     * @return Session $session
     */
    public function getSessionStore()
    {
        return $this->session;
    }

    /**
     * Set the session store implementation.
     *
     *
     * @return $this
     */
    public function setSessionStore(Session $session): self
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return View|mixed
     *
     * @throws BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (static::hasComponent($method)) {
            return $this->componentCall($method, $parameters);
        }

        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
    }
}
