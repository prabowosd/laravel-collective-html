<?php

use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Mockery as m;

class CompatibilityTest extends PHPUnit\Framework\TestCase
{
    public function tearDown(): void
    {
        m::close();
    }

    public function testHtmlBuilderConstructorWithNullUrl()
    {
        $viewFactory = m::mock(Factory::class);
        
        // This should not throw any deprecation or error in PHP 8.4
        $htmlBuilder = new HtmlBuilder($viewFactory, null);
        
        $this->assertInstanceOf(HtmlBuilder::class, $htmlBuilder);
    }

    public function testFormBuilderConstructorWithNullRequest()
    {
        $htmlBuilder = m::mock(HtmlBuilder::class);
        $urlGenerator = m::mock(UrlGenerator::class);
        $viewFactory = m::mock(Factory::class);
        
        // This should not throw any deprecation or error in PHP 8.4
        $formBuilder = new FormBuilder($htmlBuilder, $urlGenerator, $viewFactory, 'csrf-token', null);
        
        $this->assertInstanceOf(FormBuilder::class, $formBuilder);
    }
}
