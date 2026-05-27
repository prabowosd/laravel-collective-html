<?php

use Collective\Html\Components\FormCheckbox;
use Collective\Html\Components\FormInput;
use Collective\Html\Components\FormSelect;
use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\MessageBag;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class FormComponentsTest extends TestCase
{
    protected $form;

    protected $viewFactory;

    protected function setUp(): void
    {
        $url = new UrlGenerator(new RouteCollection, Request::create('/foo', 'GET'));
        $this->viewFactory = m::mock(Factory::class);
        $html = new HtmlBuilder($this->viewFactory, $url);
        $this->form = new FormBuilder($html, $url, $this->viewFactory, 'abc');
    }

    protected function tearDown(): void
    {
        m::close();
    }

    protected function withErrors(array $messages): void
    {
        $this->viewFactory->shouldReceive('shared')->with('errors')->andReturn(new MessageBag($messages));
    }

    public function test_input_renders_label_control_and_feedback_on_error()
    {
        $this->withErrors(['email' => ['Required.']]);

        $html = (string) (new FormInput($this->form, 'email', 'Email', 'email'))->toHtml();

        $this->assertStringContainsString('<label for="email" class="form-label">Email</label>', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('form-control is-invalid', $html);
        $this->assertStringContainsString('<div class="invalid-feedback">Required.</div>', $html);
    }

    public function test_input_without_error_is_clean()
    {
        $this->withErrors([]);

        $html = (string) (new FormInput($this->form, 'name', 'Full Name'))->toHtml();

        $this->assertStringContainsString('class="form-control"', $html);
        $this->assertStringNotContainsString('is-invalid', $html);
        $this->assertStringNotContainsString('invalid-feedback', $html);
    }

    public function test_input_merges_passthrough_attributes()
    {
        $this->withErrors([]);

        $html = (string) (new FormInput($this->form, 'name', 'Full Name'))
            ->withAttributes(['placeholder' => 'Type here', 'class' => 'w-50'])
            ->toHtml();

        $this->assertStringContainsString('placeholder="Type here"', $html);
        $this->assertStringContainsString('form-control w-50', $html);
    }

    public function test_textarea_type_renders_textarea()
    {
        $this->withErrors([]);

        $html = (string) (new FormInput($this->form, 'bio', 'Bio', 'textarea'))->toHtml();

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('form-control', $html);
    }

    public function test_select_from_options_array()
    {
        $this->withErrors([]);

        $html = (string) (new FormSelect($this->form, 'role', 'Role', ['a' => 'Admin', 'm' => 'Member'], selected: 'm'))->toHtml();

        $this->assertStringContainsString('class="form-select"', $html);
        $this->assertStringContainsString('<option value="m" selected="selected">Member</option>', $html);
        $this->assertStringContainsString('<label for="role" class="form-label">Role</label>', $html);
    }

    public function test_checkbox_uses_form_check_markup()
    {
        $this->withErrors([]);

        $html = (string) (new FormCheckbox($this->form, 'agree', 'I agree', 1, true))->toHtml();

        $this->assertStringContainsString('<div class="form-check">', $html);
        $this->assertStringContainsString('class="form-check-input"', $html);
        $this->assertStringContainsString('checked="checked"', $html);
        $this->assertStringContainsString('<label for="agree" class="form-check-label">I agree</label>', $html);
    }
}
