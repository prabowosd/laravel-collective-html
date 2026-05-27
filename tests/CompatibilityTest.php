<?php

use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
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
}
