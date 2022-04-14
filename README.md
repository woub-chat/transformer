# Eloquent transformer

## Install
```bash
composer require bfg/transformer
```

## Description
The package is designed to transform data `for` the model (on the example obtained by the data from the API). And data `from` the model (for example, when you need to send data back on the API).

## Usage

### Create new transformer
```bash
php artisan make:transformer UserTransformer -m User
```
Describe all the fields that you will fill in the new class `App\Transformers\UserTransformer` in the `$toModel` variable:
```php
use Bfg\Transformer\Transformer;
...
class UserTransformer extends Transformer
{
    /**
     * If your data that you received from 
     * a third-party source have an Identifier, 
     * then you need to specify this field.
     * @var string|null 
     */
    protected ?string $remoteId = "ID";

    /**
     * An alternative source indicate the model 
     * if it is not sent to the transformer.
     * @var string|null 
     */
    protected ?string $modelClass = User::class;

    /**
     * Mapping to send data generation into the model.
     * @var string[]
     */
    protected array $toModel = [
        'FullName' => 'name',
        'Email' => 'email',
        
        // or if you identical key names:
        'name',
        'email',
        
        // for related iteration
        ContactsTransformer::class // The transformer of contacts 
            => 'contacts', // The relation name in the model
    ];
    
    /**
     * Mapping for the direction of data generation 
     * from the model, back to third-party service.
     * @var string[]
     */
    protected array $fromModel = [
        'name' => ['FirstName', 'LastName'],
        'address' => 'Address',
        'email' => 'Email',
        'phone' => 'Phone',
    ];
    
    /**
     * To implement the data unloading mechanism 
     * on third-party service.
     * @return void
     */
    public function upload()
    {

    }
}
```

### Mutators
For fully transformation, it is often necessary to process a little more accurately, for this there are mutators in different directions:
```php
use Bfg\Transformer\Transformer;
...
class UserTransformer extends Transformer
{
    ...
    protected array $toModel = [
        'FullName' => 'name',
        'Email' => 'email',
    ];
    
    protected array $fromModel = [
        'name' => ['FirstName', 'LastName'],
        'email' => 'Email',
    ];
    
    protected function toNameAttribute($dataValue)
    {
        $this->data; // All data is available on this property.
        $this->model; // The model is available on this property.
        
        return $dataValue;
    }
    
    protected function fromNameAttribute($modelValue)
    {    
        return $modelValue;
    }
    
    protected function forFirstNameDataAttribute($modelValue)
    {    
        return $modelValue;
    }
    
    protected function forLastNameDataAttribute($modelValue)
    {    
        return $modelValue;
    }
    
    protected function forEmailDataAttribute($modelValue)
    {    
        return $modelValue;
    }
}
```

### Casting
All transformation casting rules are completely copied from the
[casting of `Laravel Attributes`](https://laravel.com/docs/8.x/eloquent-mutators#attribute-casting)
and their functionality is absolutely identical (except for the `set` of custom casts):
```php
...
    protected $casts = [
        'views' => 'int'
    ];
...
```
> Applies to data, not to the model.


### Model catch
In order to catch a model definition for a transformer (based on data), you can use the `getModel` method:
```php
use Bfg\Transformer\Transformer;
use App\Models\User;
...
class UserTransformer extends Transformer
{
    ...
    protected function getModel()
    {    
        return User::where('remote_id', $this->data['ID'])->first()
            ?: parent::getModel();
    }
}
```

### Data catch
For a transformer, you can determine the value for processing directly inside, 
this mechanism allows you to generate built-in dependent nesting. 
According to the rules, if a collection `Illuminate\Support\Collection` is returned, 
then the selection of a transformer instance will be created for each entry.
```php
use Bfg\Transformer\Transformer;
use App\Models\User;
...
/**
 * @property-read ApiService $api For example some "ApiService" class
 */
class UserTransformer extends Transformer
{
    ...
    protected function getData()
    {    
        return $this->api->findUser($this->model->remote_id);
    }
}
```

### Data to model
```php
use App\Transformers\UserTransformer;
...
$data = [
    'userName' => 'Thomas',
    'userEmail' => 'thomas@example.com',
]; // for example, any data

$model = UserTransformer::make()
    ->withData($data)
    ->toModel(); // Instance of User model
    
// Or from any model
$model = UserTransformer::make()
    ->withData($data)
    ->withModel(User::find(1))
    ->toModel(); // With my instance of User model

$model->save();
```

### Data from model
```php
use App\Models\User;
use App\Transformers\UserTransformer;
...
$model = User::find(1);

$data = UserTransformer::make()->withModel($model)->toData()->data; 
    // => ['userName' => 'Thomas','userEmail' => 'thomas@example.com']

// Or with you data filling
$fillData = (object) ['userName' => null, 'userEmail' => null, 'otherData' => 'test']
UserTransformer::make()->withModel($model)->withData($fillData)->toData()->upload();
```

Next, the collection perceives all the methods of the model to the entire collection:
```php
use App\Transformers\TransformerCollection;
...
/** @var TransformerCollection $collection */
$collection->save();
```
To start the entire chain in the transaction:
```php
use App\Transformers\TransformerCollection;
...
/** @var TransformerCollection $collection */
$collection->transaction()->save();
// For additional updating
$collection->transaction()->save()->update([
    'api_updated_at' => now()
]);
```
