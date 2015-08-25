# Getting data into your models
Querying data isn't really Ornament's job, we think. _Say what?_ Well, the 'M'
in ORM stands for 'mapper' after all - its job is to _map_ data to your models,
not to actually _retrieve_ it. (Otherwise they'd have called it "Object
Mapper-Getter", which admittedly would have made for a cute acronym.)

Having said that, however, adapters do need to implement a `load` method. You
can utilise that to facilitate a form of autoloading.

```php
<?php

use Ornament\Pdo;

class SimpleModel
{
    use Pdo;

    public $id;
    public $foo;

    public function __construct(PDO $pdo)
    {
        $this->addPdoAdapter($pdo);
    }
}

$model = new SimpleModel($pdo);
$model->id = 1;
$model->load();
```

## Loading an array of models
The Ornament models also provide a simple `query` method which returns an array of
models matching the supplied key/value pair of properties:

```php
<?php

$model = new SimpleModel($pdo);
$list = $model->query(['foo' => 'bar']);
```

The implementation of how to convert the key/value pairs into something
meaningful is of course left to the adapter; for the default PDO adapter,
it's simply `WHERE key = :value`. If you need something more complicated you
should write your own implementation of `query`.

## Auto-loading one-to-one relationships
Often you'll want to automagically load related objects, e.g. when an
`ItemModel` has an `owner` propery that is actually a `UserModel`. For this,
your models can use the `Autoload` trait and you should annotate your properties
accordingly:

```php
<?php

class ItemModel
{
    use Ornament\Pdo;
    use Ornament\Autoload;

    /**
     * @Model UserModel
     * @Mapping id = owner
     * @Constructor [ pdo ]
     */
    public $owner;

    // etc
}
```

The default `@Mapping` is "map autoloaded fieldname to id on the new model",
which normally makes sense. You only have to explicitly specify this if your
mapping is different (which it shouldn't be for 99% of the cases).

The `@Constructor` annotation lets you pass properties on `$this` (the calling
model) to the constructor of the child model. This is useful for single-adapter
projects; if your project grows and starts mixing adapters, you should consider
[dependency injection](http://disclosure.monomelodies.nl) for this and use
argument-less constructors.

## Auto-loading one-to-many relationships
The reverse can also happen: for each `UserModel`, you want all `ItemModel`s
she "owns". This is similar; you only need to pluralize the annotation:

```php
<?php

class UserModel
{
    use Ornament\Pdo;
    use Ornament\Autoload;

    /**
     * @Models ItemModel
     * @Mapping owner = id
     * @Constructor [ pdo ]
     */
    public $items = [];

    // etc
}
```

> Ornament is smart enough to prevent infinite loops, but one should still take
> care when autoloading many relationships; the number of queries done can
> quickly grow out of control.
