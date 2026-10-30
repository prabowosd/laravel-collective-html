<?php

use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Collection;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class FormSourceSelectTest extends TestCase
{
    protected $formBuilder;

    protected function setUp(): void
    {
        // Enums are a parse error before PHP 8.1, so they live in their own file.
        if (PHP_VERSION_ID >= 80100) {
            require_once __DIR__.'/Fixtures/SourceSelectEnums.php';
        }

        $urlGenerator = new UrlGenerator(new RouteCollection, Request::create('/foo', 'GET'));
        $viewFactory = m::mock(Factory::class);
        $htmlBuilder = new HtmlBuilder($viewFactory, $urlGenerator);
        $this->formBuilder = new FormBuilder($htmlBuilder, $urlGenerator, $viewFactory, 'abc');
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function test_enum_select_humanizes_case_names_by_default()
    {
        $this->requiresEnums();

        $select = (string) $this->formBuilder->enumSelect('status', SourceSelectStatus::class, 'inactive');

        $this->assertStringContainsString('<select name="status">', $select);
        $this->assertStringContainsString('<option value="active">Active</option>', $select);
        $this->assertStringContainsString('<option value="inactive" selected="selected">Inactive</option>', $select);
    }

    public function test_enum_select_uses_label_method_when_present()
    {
        $this->requiresEnums();

        $select = (string) $this->formBuilder->enumSelect('role', SourceSelectRole::class, 1);

        $this->assertStringContainsString('<option value="1" selected="selected">Administrator</option>', $select);
        $this->assertStringContainsString('<option value="2">Team Member</option>', $select);
    }

    public function test_model_select_from_collection()
    {
        $source = new Collection([
            (object) ['id' => 10, 'name' => 'Alpha'],
            (object) ['id' => 20, 'name' => 'Beta'],
        ]);

        $select = (string) $this->formBuilder->modelSelect('category_id', $source, 'name', 'id', 20);

        $this->assertStringContainsString('<option value="10">Alpha</option>', $select);
        $this->assertStringContainsString('<option value="20" selected="selected">Beta</option>', $select);
    }

    public function test_model_select_from_array_uses_default_columns()
    {
        $source = [
            ['id' => 1, 'name' => 'One'],
            ['id' => 2, 'name' => 'Two'],
        ];

        $select = (string) $this->formBuilder->modelSelect('n', $source);

        $this->assertStringContainsString('<option value="1">One</option>', $select);
        $this->assertStringContainsString('<option value="2">Two</option>', $select);
    }

    public function test_case_names_become_headlines_on_every_laravel_version()
    {
        $form = new class(m::mock(HtmlBuilder::class), m::mock(UrlGenerator::class), m::mock(Factory::class), 'abc') extends FormBuilder
        {
            public function headlineFor(string $name): string
            {
                return $this->headline($name);
            }
        };

        $this->assertSame('On Hold', $form->headlineFor('OnHold'));
        $this->assertSame('Active', $form->headlineFor('Active'));
        $this->assertSame('Partly Shipped', $form->headlineFor('partly_shipped'));
    }

    protected function requiresEnums(): void
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enums need PHP 8.1.');
        }
    }
}
