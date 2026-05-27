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

enum SourceSelectStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum SourceSelectRole: int
{
    case Admin = 1;
    case Member = 2;

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Member => 'Team Member',
        };
    }
}

class FormSourceSelectTest extends TestCase
{
    protected $formBuilder;

    protected function setUp(): void
    {
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
        $select = (string) $this->formBuilder->enumSelect('status', SourceSelectStatus::class, 'inactive');

        $this->assertStringContainsString('<select name="status">', $select);
        $this->assertStringContainsString('<option value="active">Active</option>', $select);
        $this->assertStringContainsString('<option value="inactive" selected="selected">Inactive</option>', $select);
    }

    public function test_enum_select_uses_label_method_when_present()
    {
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
}
