<?php

use Ornament\Model;

class DummyModel
{
    use Ornament\Storage;

    public $id;
    public $name;
    public $comment;
    
    public function __construct()
    {
        global $adapter;
        $this->addAdapter($adapter, 'dummy', ['id', 'name', 'comment']);
    }
}

class Adapter implements Ornament\Adapter
{
    private $storage = [];

    public function query($model, array $parameters, array $opts = [], array $ctor = [])
    {
        $ret = [];
        foreach ($this->storage as $model) {
            foreach ($parameters as $key => $value) {
                if ($model->$key != $value) {
                    continue 2;
                }
            }
            $ret[] = $model;
        }
        return $ret;
    }
    
    public function load(Model $model)
    {
        $model = $this->storage[$model->id];
    }
    
    public function create(Model $model)
    {
        $model->id = count($this->storage) + 1;
        $this->storage[$model->id] = $model;
    }
    
    public function update(Model $model)
    {
        $this->storage[$model->id] = $model;
    }
    
    public function delete(Model $model)
    {
        unset($this->storage[$model->id]);
    }
}

$adapter = new Adapter;

class CollectionTest extends PHPUnit_Framework_TestCase
{
    public function testCollection()
    {
        global $adapter;
        $one = new DummyModel;
        $one->comment = 'foo';
        $two = new DummyModel;
        $two->comment = 'bar';
        $items = [$one, $two];
        $collection = new Ornament\Collection($items);
        $collection->save();
        $this->assertEquals(2, count($collection));
        unset($collection[0]);
        $this->assertEquals(1, count($collection));
        $this->assertEquals(2, count($adapter->query($two, [])));
        $collection->save();
        $this->assertEquals(1, count($adapter->query($two, [])));
    }

    public function testEmptyCollection()
    {
        $collection = new Ornament\Collection(false);
    }
}

