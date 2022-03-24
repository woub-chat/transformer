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
...
class UserTransformer extends Transformer
{
    protected $model = User::class;

    protected $toModel = [
        'userName' => 'name',
        'userEmail' => 'email',
        
        // or if you identical key names:
        'name',
        'email',
    ];
}
```

### Mutators
For fully transformation, it is often necessary to process a little more accurately, for this there are mutators in different directions:
```php
...
class UserTransformer extends Transformer
{
    ...
    protected $toModel = [
        'userName' => 'name',
        'userEmail' => 'email',
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
}
```

### Casting
All transformation casting rules are completely copied from the
[casting of `Laravel Attributes`](https://laravel.com/docs/8.x/eloquent-mutators#attribute-casting)
and their functionality is absolutely identical (except for the `set` of custom casts):
```php
...
    protected array $casts = [
        'views' => 'int'
    ];
...
```
> Applies to data, not to the model.

### Model catch
In order to catch a model definition for a transformer (based on data), you can use the `getModel` method:
```php
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

### Data to model
```php
use App\Transformers\UserTransformer;
...
$data = [
    'userName' => 'Thomas',
    'userEmail' => 'thomas@example.com',
]; // for example, any data

$model = UserTransformer::toModel($data); // Instance of User model
// Or from any model
$model = UserTransformer::toModel($data, User::find(1)); // With my instance of User model

$model->save();
```

### Data from model
```php
use App\Models\User;
use App\Transformers\UserTransformer;
...
$model = User::find(1);

$data = UserTransformer::fromModel($model); 
    // => ['userName' => 'Thomas','userEmail' => 'thomas@example.com']

// Or with you data filling
$fillData = (object) ['userName' => null, 'userEmail' => null, 'otherData' => 'test']
$data = UserTransformer::fromModel($model, $fillData); 
    // => {
    //      +"userName": "Thomas",
    //      +"userEmail": "thomas@example.com",
    //      +"otherData": "test",
    //    }

```

### Data to model collection
```php
use App\Models\User;
use App\Transformers\UserTransformer;
use App\Transformers\TransformerCollection;
...
$datas = [
    [
        'userName' => 'Thomas',
        'userEmail' => 'thomas@example.com',
    ],
    [
        'userName' => 'Ali',
        'userEmail' => 'ali@example.com',
    ],
]; // for example, any data

/** @var TransformerCollection $collection */
$collection = UserTransformer::toModelCollection($datas); // The collection instance of models Instances
    // => Bfg\Transformer\TransformerCollection {
    //      all: [
    //          App\Models\User {
    //              userName: "Thomas",
    //              userEmail: "thomas@example.com"
    //          },
    //          App\Models\User {
    //              userName: "Ali",
    //              userEmail: "ali@example.com"
    //          },
    //      ]
    //    }
   
// Or with ready collection of models   
$collection = UserTransformer::toModelCollection($datas, User::where('active', 1)->get());
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

### Data from model collection
```php
use App\Models\User;
use App\Transformers\UserTransformer;
use App\Transformers\TransformerCollection;
...
$modelCollection = User::where('active', 1)->get();

$data = UserTransformer::fromModelCollection($modelCollection); 
    // => Bfg\Transformer\TransformerCollection {
    //      all: [
    //          ['userName' => 'Thomas','userEmail' => 'thomas@example.com'],
    //          ['userName' => 'Ali','userEmail' => 'ali@example.com'],
    //      ]
    //    }

// Or with you data filling
$fillData = (object) [
    ['userName' => null, 'userEmail' => null, 'otherData' => 'test'],
    ['userName' => null, 'userEmail' => null, 'otherData' => 'test2'],
];
$data = UserTransformer::fromModelCollection($modelCollection, $fillData); 
    // => {
    //      ['userName' => 'Thomas','userEmail' => 'thomas@example.com', 'otherData' => 'test'],
    //      ['userName' => 'Ali','userEmail' => 'ali@example.com', 'otherData' => 'test2'],
    //    }
```
