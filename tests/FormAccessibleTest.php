<?php

use Carbon\Carbon;
use Collective\Html\Eloquent\FormAccessible;
use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class FormAccessibleTest extends TestCase
{
    protected $now;

    protected $modelData;

    protected $urlGenerator;

    protected $viewFactory;

    protected $htmlBuilder;

    protected $formBuilder;

    protected function setUp(): void
    {
        Capsule::table('models')->truncate();
        Model::unguard();

        $this->now = Carbon::now();

        $this->modelData = [
            'string' => 'abcdefghijklmnop',
            'email' => 'tj@tjshafer.com',
            'address' => [
                'street' => 'abcde st',
            ],
            'array' => [1, 2, 3],
            'transform_key' => 'testing testing',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ];

        $this->urlGenerator = new UrlGenerator(new RouteCollection, Request::create('/foo', 'GET'));
        $this->viewFactory = m::mock(Factory::class);
        $this->htmlBuilder = new HtmlBuilder($this->viewFactory, $this->urlGenerator);
        $this->formBuilder = new FormBuilder($this->htmlBuilder, $this->urlGenerator, $this->viewFactory, 'abc');
    }

    public function test_it_can_mutate_values_for_forms()
    {
        $model = new ModelThatUsesForms($this->modelData);
        $user = new User(['name' => 'Anton']);
        $model->setRelation('user', $user);
        $this->formBuilder->setModel($model);

        $this->assertEquals($model->getFormValue('string'), 'ponmlkjihgfedcba');
        $this->assertEquals($model->getFormValue('created_at'), $this->now->timestamp);

        $this->assertEquals($user, $model->getFormValue('user'));
        $this->assertEquals('Get name: Anton', $model->getFormValue('user')->name);
        $this->assertEquals('Get name for form: Anton', $model->getFormValue('user.name'));
    }

    public function test_it_can_mutate_related_values_for_forms()
    {
        $model = new ModelThatUsesForms($this->modelData);
        $relatedModel = new ModelThatUsesForms($this->modelData);
        $relatedModel->address = [
            'street' => '123 Evergreen Terrace',
        ];
        $model->setRelation('related', $relatedModel);

        $this->formBuilder->setModel($model);

        $this->assertEquals($this->formBuilder->getValueAttribute('related[string]'), 'ponmlkjihgfedcba');
        $this->assertEquals($this->formBuilder->getValueAttribute('related[address][street]'), '123 Evergreen Terrace');
    }

    public function test_it_can_get_related_value_for_forms()
    {
        $model = new ModelThatUsesForms($this->modelData);
        $this->assertEquals($model->getFormValue('address.street'), 'abcde st');
    }

    public function test_it_can_use_get_accessor_values_when_there_are_no_form_accessors()
    {
        $model = new ModelThatUsesForms($this->modelData);
        $this->formBuilder->setModel($model);

        $this->assertEquals($this->formBuilder->getValueAttribute('email'), 'mutated@tjshafer.com');
    }

    public function test_it_returns_same_result_with_and_without_this_feature()
    {
        $modelWithAccessor = new ModelThatUsesForms($this->modelData);
        $modelWithoutAccessor = new ModelThatDoesntUseForms($this->modelData);

        $this->formBuilder->setModel($modelWithAccessor);
        $valuesWithAccessor[] = $this->formBuilder->getValueAttribute('array');
        $valuesWithAccessor[] = $this->formBuilder->getValueAttribute('array[0]');
        $valuesWithAccessor[] = $this->formBuilder->getValueAttribute('transform.key');
        $this->formBuilder->setModel($modelWithoutAccessor);
        $valuesWithoutAccessor[] = $this->formBuilder->getValueAttribute('array');
        $valuesWithoutAccessor[] = $this->formBuilder->getValueAttribute('array[0]');
        $valuesWithoutAccessor[] = $this->formBuilder->getValueAttribute('transform.key');

        $this->assertEquals($valuesWithAccessor, $valuesWithoutAccessor);
    }

    public function test_it_can_still_mutate_values_for_views()
    {
        $model = new ModelThatUsesForms($this->modelData);
        $this->formBuilder->setModel($model);

        $this->assertEquals($model->string, 'ABCDEFGHIJKLMNOP');
        $this->assertEquals($model->created_at, '1 second ago');
    }

    public function test_it_doesnt_require_the_use_of_this_feature()
    {
        $model = new ModelThatDoesntUseForms($this->modelData);
        $this->formBuilder->setModel($model);

        $this->assertEquals($model->string, 'ABCDEFGHIJKLMNOP');
        $this->assertEquals($model->created_at, '1 second ago');
    }
}

class ModelThatUsesForms extends Model
{
    use FormAccessible;

    protected $table = 'models';

    public function formStringAttribute($value)
    {
        return strrev($value);
    }

    public function getStringAttribute($value)
    {
        return strtoupper($value);
    }

    public function formCreatedAtAttribute(Carbon $value)
    {
        return $value->timestamp;
    }

    public function getCreatedAtAttribute($value)
    {
        return '1 second ago';
    }

    public function getEmailAttribute($value)
    {
        return 'mutated@tjshafer.com';
    }
}

class ModelThatDoesntUseForms extends Model
{
    protected $table = 'models';

    public function getStringAttribute($value)
    {
        return strtoupper($value);
    }

    public function getCreatedAtAttribute($value)
    {
        return '1 second ago';
    }
}

class User extends Model
{
    use FormAccessible;

    public function formNameAttribute($value)
    {
        return 'Get name for form: '.$value;
    }

    public function getNameAttribute($value)
    {
        return 'Get name: '.$value;
    }
}
