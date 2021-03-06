<?php

namespace Ornament;

use SplObjectStorage;
use ReflectionProperty;

trait Model
{
    use Annotate;
    use Identify;

    /**
     * @var array
     * Private storage of registered adapters for this model.
     * @Private
     */
    private $__adapters;

    /**
     * @var string
     * Private storage of model's current state.
     * @Private
     */
    private $__state = 'new';

    /**
     * @var array
     * Private storage of the model's primary key(s).
     */
    private $__primaryKeys;

    /**
     * Register the specified adapter for the given identifier and fields.
     *
     * Generic method to add an Ornament adapter. Specific implementations
     * should generally supply a trait with an addImplementationAdapter that
     * takes care of wrapping the adapter in an Adapter-compatible object.
     *
     * Note that a model is considered "new" if fields are already populated.
     * This works for Pdo-style adapters, since PDO::FETCH_CLASS sets values
     * _prior_ to object instantiation. For adapters using other data sources
     * (e.g. an API) you would need to correct this manually.
     *
     * @param Ornament\Adapter $adapter Adapter object implementing the
     *  Ornament\Adapter interface.
     * @param string $id Identifier for this adapter (table name, API endpoint,
     *  etc.)
     * @param array $fields Array of fields (properties) this adapter works on.
     *  Should default to "all known public non-virtual members".
     * @return Ornament\Adapter The registered adapter, for easy chaining.
     */
    protected function addAdapter(Adapter $adapter, $id = null, array $fields = null)
    {
        if (!isset($this->__adapters)) {
            $this->__adapters = new SplObjectStorage;
        }
        $annotations = $this->annotations();
        if (!isset($id)) {
            $id = isset($annotations['class']['Identifier']) ?
                $annotations['class']['Identifier'] :
                $this->guessIdentifier();
        }
        if (!isset($fields)) {
            $fields = [];
            foreach ($annotations['properties'] as $prop => $anno) {
                if ($prop{0} != '_'
                    && !(isset($anno['Virtual']) && !isset($anno['From']))
                    && !isset($anno['Private'])
                    && !is_array($this->$prop)
                ) {
                    $fields[] = $prop;
                }
                if (is_array($this->$prop)) {
                    $this->$prop = new Collection(
                        [],
                        $this,
                        isset($anno['Mapping']) ?
                            $anno['Mapping'] :
                            ['id' => $prop]
                    );
                }
            }
        }
        $pk = [];
        foreach ($annotations['properties'] as $prop => $anno) {
            if (isset($anno['PrimaryKey'])) {
                $pk[] = $prop;
            }
            if (isset($anno['Bitflag'])) {
                $this->$prop = new Bitflag(
                    $this->$prop,
                    $anno['Bitflag']
                );
            }
        }
        if (!$pk && in_array('id', $fields)) {
            $pk[] = 'id';
        }
        if ($pk) {
            $this->__primaryKeys = $pk;
            call_user_func_array([$adapter, 'setPrimaryKey'], $pk);
        }
        $adapter->setIdentifier($id)
                ->setFields($fields)
                ->setAnnotations($annotations);
        $model = new Container($adapter);
        $new = true;
        foreach ($fields as $field => $alias) {
            $fname = is_numeric($field) ? $alias : $field;
            if (in_array($fname, $pk) && isset($this->$fname)) {
                $new = false;
            }
            $model->$alias =& $this->$fname;
        }
        if ($new) {
            $model->markNew();
        } else {
            $model->markClean();
        }
        $this->__adapters->attach($model);
        foreach ($this->__adapters as $model) {
            if (!$model->isNew()) {
                $this->__state = 'clean';
            }
        }
        return $adapter;
    }

    /**
     * Get the primary key(s) for this model.
     *
     * @return mixed Either the scalar single primary key, or an array of scalar
     *  values if the model has multiple fields defined as the primary key.
     */
    public function getPrimaryKey()
    {
        $pks = [];
        foreach ($this->__primaryKeys as $pk) {
            if (is_object($this->$pk)) {
                $traits = class_uses($this->$pk);
                if (isset($traits['Ornament\Model'])
                    || isset($traits['Ornament\JsonModel'])
                ) {
                    $pks[] = $this->$pk->getPrimaryKey();
                } else {
                    $pks[] = "{$this->$pk}";
                }
            } else {
                $pks[] = $this->$pk;
            }
        }
        return count($pks) == 1 ? $pks[0] : $pks;
    }

    /**
     * (Re)loads the current model based on the specified adapters.
     * Optionally also calls methods annotated with `onLoad`.
     *
     * @param bool $includeBase If set to true, loads the base model; if false,
     *  only (re)loads linked models. Defaults to true.
     * @return void
     */
    public function load($includeBase = true)
    {
        $annotations = $this->annotations();
        if ($includeBase) {
            $errors = [];
            foreach ($this->__adapters as $model) {
                $model->load();
            }
        }
        foreach ($annotations['methods'] as $method => $anns) {
            if (isset($anns['onLoad'])) {
                $this->$method($annotations['properties']);
            }
        }
        foreach ($annotations['properties'] as $prop => $anns) {
            if (isset($anns['Bitflag'])) {
                $this->$prop = new Bitflag(
                    (int)("{$this->$prop}"),
                    $anns['Bitflag']
                );
            }
        }
    }

    /**
     * Returns true if any of the associated containers is new.
     *
     * @return bool
     */
    public function isNew()
    {
        foreach ($this->__adapters as $model) {
            if ($model->isNew()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if any of the associated containers is dirty.
     *
     * @return bool
     */
    public function isDirty()
    {
        foreach ($this->__adapters as $model) {
            if ($model->isDirty()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if a specific property on the model is dirty.
     *
     * @return bool
     */
    public function isModified($property)
    {
        foreach ($this->__adapters as $model) {
            if (property_exists($model, $property)) {
                return $model->isModified($property);
            }
        }
    }
    
    /**
     * Persists the model back to storage based on the specified adapters.
     * If an adapter supports transactions, you are encouraged to use them;
     * but you should do so in your own code.
     *
     * @return null|array null on success, or an array of errors encountered.
     * @throws Ornament\Exception\Immutable if the model implements the
     *  Immutable interface and is thus immutable.
     * @throws Ornament\Exception\Uncreateable if the model is new and implemnts
     *  the Uncreatable interface and can therefor not be created
     *  programmatically.
     */
    public function save()
    {
        if ($this instanceof Immutable) {
            throw new Exception\Immutable($this);
        }
        $errors = [];
        if (method_exists($this, 'notify')) {
            $notify = clone $this;
        }
        foreach ($this->__adapters as $model) {
            if ($model->isDirty()) {
                if ($model->isNew() && $this instanceof Uncreateable) {
                    throw new Exception\Uncreateable($this);
                }
                if (!$model->save()) {
                    $errors[] = true;
                }
            }
        }
        $annotations = $this->annotations()['properties'];
        foreach ($annotations as $prop => $anns) {
            if (isset($anns['Private']) || $prop{0} == '_') {
                continue;
            }
            $value = $this->$prop;
            if (is_object($value)) {
                if ($value instanceof Collection && $value->isDirty()) {
                    if ($error = $value->save()) {
                        $errors = array_merge($errors, $error);
                    }
                } elseif ($save = Helper::modelSaveMethod($value)
                    and !method_exists($value, 'isDirty') || $value->isDirty()
                ) {
                    if (!$value->$save()) {
                        $errors[] = true;
                    }
                }
            }
        }
        if (isset($notify)) {
            $notify->notify();
        }
        $this->markClean();
        return $errors ? $errors : null;
    }

    /**
     * Deletes the current model from storage based on the specified adapters.
     * If an adapter supports transactions, you are encouraged to use them;
     * but you should do so in your own code.
     *
     * @return null|array null on success, or an array of errors encountered.
     * @throw Ornament\Exception\Undeleteable if the model implements the
     *  Undeleteable interface and is hence "protected".
     */
    public function delete()
    {
        if (method_exists($this, 'notify')) {
            $notify = clone $this;
        }
        if ($this instanceof Undeleteable) {
            throw new Exception\Undeleteable($this);
        }
        $errors = [];
        foreach ($this->__adapters as $adapter) {
            if ($error = $adapter->delete($this)) {
                $errors[] = $error;
            } else {
                $adapter->markDeleted();
            }
        }
        if (isset($notify)) {
            $notify->notify();
        }
        $this->__state = 'deleted';
        return $errors ? $errors : null;
    }

    /**
     * Get the current state of the model (new, clean, dirty or deleted).
     *
     * @return string The current state.
     */
    public function state()
    {
        // Do just-in-time checking for clean/dirty:
        if ($this->__state == 'clean') {
            foreach ($this->__adapters as $model) {
                if ($model->isDirty()) {
                    $this->__state = 'dirty';
                    break;
                }
            }
        }
        return $this->__state;
    }

    /**
     * Mark the current model as 'clean', i.e. not dirty. Useful if you manually
     * set values after loading from storage that shouldn't count towards
     * "dirtiness". Called automatically after saving.
     *
     * @return void
     */
    public function markClean()
    {
        foreach ($this->__adapters as $model) {
            $model->markClean();
        }
        $annotations = $this->annotations()['properties'];
        foreach ($annotations as $prop => $anns) {
            if (isset($anns['Private']) || $prop{0} == '_') {
                continue;
            }
            $value = $this->$prop;
            if (is_object($value) and method_exists($value, 'markClean')) {
                $value->markClean();
            }
        }
        $this->__state = 'clean';
    }

    /**
     * You'll want to specify a custom implementation for this. For models in an
     * array (on another model, of course) it is called with the current index.
     * Obviously, overriding is only needed if the index is relevant.
     *
     * @param integer $index The current index in the array.
     * @return void
     */
    public function __index($index)
    {
    }
}

