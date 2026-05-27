# Laravel Collective HTML

[![Latest Stable Version](https://poser.pugx.org/prabowosd/laravel-collective-html/v/stable)](https://packagist.org/packages/prabowosd/laravel-collective-html)
[![Total Downloads](https://poser.pugx.org/prabowosd/laravel-collective-html/downloads)](https://packagist.org/packages/prabowosd/laravel-collective-html)
[![License](https://poser.pugx.org/prabowosd/laravel-collective-html/license)](https://packagist.org/packages/prabowosd/laravel-collective-html)

HTML and Form Builders for the Laravel Framework, maintained for **Laravel 10, 11, 12, and 13** and **PHP 8.2+ (including PHP 8.4)**.

The `Collective\Html` namespace is retained so the package stays a drop-in replacement for projects already using the classic HTML/Form builders.

On top of the classic API, this fork adds a few conveniences:

- **Validation helpers** — `Form::error()`, `Form::hasError()`, `Form::withValidationClass()`.
- **Selects from enums & models** — `Form::enumSelect()`, `Form::modelSelect()`.
- **Blade components** — `<x-form-input>`, `<x-form-select>`, `<x-form-checkbox>` that bundle label + control + validation feedback.
- **Configurable CSS classes** — Bootstrap by default, remappable via `config/html.php`.

## Installation

You can install this package via composer:

```bash
composer require prabowosd/laravel-collective-html
```

## Documentation

The API mirrors the classic Laravel HTML/Form builders — `Form::` and `Html::` facades behave as documented in the long-standing HTML builder docs.

## Quick Start

### Form Open
```php
{!! Form::open(['url' => 'foo/bar']) !!}
    //
{!! Form::close() !!}
```

### Label
```php
{!! Form::label('email', 'E-Mail Address') !!}
```

### Text, Text Area, Password & Hidden Fields
```php
{!! Form::text('username') !!}
{!! Form::textarea('description') !!}
{!! Form::password('password') !!}
{!! Form::hidden('invisible', 'secret') !!}
```

### Checkboxes and Radio Buttons
```php
{!! Form::checkbox('name', 'value') !!}
{!! Form::radio('name', 'value') !!}
```

### Drop-Down Lists
```php
{!! Form::select('size', ['L' => 'Large', 'S' => 'Small']) !!}
```

### Buttons
```php
{!! Form::submit('Click Me!') !!}
```

## Validation Errors

These helpers read the validation error bag that Laravel shares with every view (the `$errors` `MessageBag`). Array-style field names are resolved to dot notation automatically, so `person[name]` maps to the `person.name` error key.

```php
{{-- Render the feedback element only when the field has an error --}}
{!! Form::error('email') !!}            // <div class="invalid-feedback">The email field is required.</div>

@if (Form::hasError('email'))
    ...
@endif

{{-- First error message, or null --}}
{{ Form::getError('email') }}

{{-- Append the "invalid" class to your own options when the field errors --}}
{!! Form::text('email', null, Form::withValidationClass('email', ['class' => 'form-control'])) !!}
```

The classic `Form::` calls are left untouched — the invalid class is only added when you opt in through `withValidationClass()` (or through the Blade components below).

## Selects From Enums & Models

```php
{{-- PHP enum: backed enums use their value; a label() method is honored, --}}
{{-- otherwise the case name is humanized.                                 --}}
{!! Form::enumSelect('status', App\Enums\OrderStatus::class, $selected) !!}

{{-- Eloquent: pass a model class, query/relation, collection, or array --}}
{!! Form::modelSelect('category_id', App\Models\Category::class, 'name', 'id', $selected) !!}
{!! Form::modelSelect('category_id', $categories /* a Collection */, 'title', 'uuid') !!}
```

Add a label provider to an enum to control option text:

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
```

## Blade Components

Class-based components that compose a label, the control, and the validation feedback in one tag. Pass-through HTML attributes (`placeholder`, `class`, `data-*`, …) are merged onto the control.

```blade
<x-form-input name="email" label="Email" type="email" placeholder="you@example.com" />
<x-form-input name="bio" label="Bio" type="textarea" rows="4" />

<x-form-select name="role" label="Role" :options="['admin' => 'Admin', 'member' => 'Member']" selected="member" />
<x-form-select name="status" label="Status" :enum="App\Enums\OrderStatus::class" />
<x-form-select name="category_id" label="Category" :model="App\Models\Category::class" label-column="name" value-column="id" />

<x-form-checkbox name="agree" label="I accept the terms" :checked="true" />
```

When a field has a validation error, components automatically add the configured `invalid` class to the control and render the feedback element after it.

## Configuration

The CSS classes used for controls and validation state default to Bootstrap. Publish the config to remap them (e.g. for Tailwind with `@tailwindcss/forms`):

```bash
php artisan vendor:publish --tag=html-config
```

```php
// config/html.php
return [
    'classes' => [
        'control' => 'form-control',
        'select' => 'form-select',
        'label' => 'form-label',
        'invalid' => 'is-invalid',
        'feedback' => 'invalid-feedback',
        'check_wrapper' => 'form-check',
        'check_input' => 'form-check-input',
        'check_label' => 'form-check-label',
    ],
];
```

## Testing

```bash
composer test      # phpunit
composer format    # pint
composer lint      # pint --test
```

## License

The Laravel Collective HTML package is open-sourced software licensed under the [MIT license](LICENSE.txt).
