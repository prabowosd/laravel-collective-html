# Laravel Collective HTML

[![Latest Stable Version](https://poser.pugx.org/prabowosd/laravel-collective-html/v/stable)](https://packagist.org/packages/prabowosd/laravel-collective-html)
[![Total Downloads](https://poser.pugx.org/prabowosd/laravel-collective-html/downloads)](https://packagist.org/packages/prabowosd/laravel-collective-html)
[![License](https://poser.pugx.org/prabowosd/laravel-collective-html/license)](https://packagist.org/packages/prabowosd/laravel-collective-html)

HTML and Form Builders for the Laravel Framework, maintained for **Laravel 10, 11, 12, and 13** and **PHP 8.2+ (including PHP 8.4)**.

The `Collective\Html` namespace is retained so the package stays a drop-in replacement for projects already using the classic HTML/Form builders.

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

## License

The Laravel Collective HTML package is open-sourced software licensed under the [MIT license](LICENSE.txt).
