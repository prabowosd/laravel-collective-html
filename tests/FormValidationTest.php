<?php

use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\MessageBag;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class FormValidationTest extends TestCase
{
    protected $formBuilder;

    protected $viewFactory;

    protected function setUp(): void
    {
        $urlGenerator = new UrlGenerator(new RouteCollection, Request::create('/foo', 'GET'));
        $this->viewFactory = m::mock(Factory::class);
        $htmlBuilder = new HtmlBuilder($this->viewFactory, $urlGenerator);
        $this->formBuilder = new FormBuilder($htmlBuilder, $urlGenerator, $this->viewFactory, 'abc');
    }

    protected function tearDown(): void
    {
        m::close();
    }

    protected function withErrors(array $messages): void
    {
        $this->viewFactory->shouldReceive('shared')->with('errors')->andReturn(new MessageBag($messages));
    }

    public function test_has_error_and_get_error()
    {
        $this->withErrors(['email' => ['The email field is required.']]);

        $this->assertTrue($this->formBuilder->hasError('email'));
        $this->assertFalse($this->formBuilder->hasError('name'));
        $this->assertSame('The email field is required.', $this->formBuilder->getError('email'));
        $this->assertNull($this->formBuilder->getError('name'));
    }

    public function test_error_renders_feedback_only_when_present()
    {
        $this->withErrors(['email' => ['Required.']]);

        $this->assertSame(
            '<div class="invalid-feedback">Required.</div>',
            (string) $this->formBuilder->error('email')
        );
        $this->assertSame('', (string) $this->formBuilder->error('name'));
    }

    public function test_with_validation_class_appends_invalid_only_on_error()
    {
        $this->withErrors(['email' => ['Required.']]);

        $this->assertSame(['class' => 'is-invalid'], $this->formBuilder->withValidationClass('email', []));
        $this->assertSame(['class' => 'form-control is-invalid'], $this->formBuilder->withValidationClass('email', ['class' => 'form-control']));
        $this->assertSame(['class' => 'form-control'], $this->formBuilder->withValidationClass('name', ['class' => 'form-control']));
    }

    public function test_nested_field_names_resolve_to_dot_notation()
    {
        $this->withErrors(['person.name' => ['Required.']]);

        $this->assertTrue($this->formBuilder->hasError('person[name]'));
        $this->assertSame('Required.', $this->formBuilder->getError('person[name]'));
    }

    public function test_no_errors_shared_is_safe()
    {
        $this->viewFactory->shouldReceive('shared')->with('errors')->andReturnNull();

        $this->assertFalse($this->formBuilder->hasError('email'));
        $this->assertNull($this->formBuilder->getError('email'));
        $this->assertSame('', (string) $this->formBuilder->error('email'));
    }
}
