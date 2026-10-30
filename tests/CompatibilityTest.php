<?php

use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\UrlGenerator;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class CompatibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_html_builder_constructor_with_null_url()
    {
        $viewFactory = m::mock(Factory::class);

        // This should not throw any deprecation or error in PHP 8.4
        $htmlBuilder = new HtmlBuilder($viewFactory, null);

        $this->assertInstanceOf(HtmlBuilder::class, $htmlBuilder);
    }

    public function test_form_builder_constructor_with_null_request()
    {
        $htmlBuilder = m::mock(HtmlBuilder::class);
        $urlGenerator = m::mock(UrlGenerator::class);
        $viewFactory = m::mock(Factory::class);

        // This should not throw any deprecation or error in PHP 8.4
        $formBuilder = new FormBuilder($htmlBuilder, $urlGenerator, $viewFactory, 'csrf-token', null);

        $this->assertInstanceOf(FormBuilder::class, $formBuilder);
    }

    public function test_repeated_fields_consume_array_old_input_without_dynamic_property()
    {
        $viewFactory = m::mock(Factory::class);
        $urlGenerator = m::mock(UrlGenerator::class);
        $session = m::mock(Session::class);
        $session->shouldReceive('getOldInput')->with('tags')->andReturn(['first', 'second']);

        $formBuilder = new FormBuilder(new HtmlBuilder($viewFactory, $urlGenerator), $urlGenerator, $viewFactory, 'csrf-token');
        $formBuilder->setSessionStore($session);

        $this->assertSame('<input name="tags[]" type="text" value="first">', $formBuilder->text('tags[]')->toHtml());
        $this->assertSame('<input name="tags[]" type="text" value="second">', $formBuilder->text('tags[]')->toHtml());
    }

    public function test_null_strings_are_treated_as_empty()
    {
        $viewFactory = m::mock(Factory::class);
        $urlGenerator = m::mock(UrlGenerator::class);
        $htmlBuilder = new HtmlBuilder($viewFactory, $urlGenerator);
        $formBuilder = new FormBuilder($htmlBuilder, $urlGenerator, $viewFactory, 'csrf-token');

        $this->assertSame('', $htmlBuilder->entities(null));
        $this->assertSame('', $htmlBuilder->decode(null));
        $this->assertSame('', $htmlBuilder->obfuscate(null));
        $this->assertSame('<label for=""></label>', $formBuilder->label(null)->toHtml());
    }
}
