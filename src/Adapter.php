<?php

namespace Ornament;

interface Adapter
{
    public function setPrimaryKey($field);
    public function setIdentifier($identifier);
    public function setFields(array $fields);
    public function query($model, array $ps, array $opts = []);
    public function load(Container $model);
    public function create(Container $model);
    public function update(Container $model);
    public function delete(Container $model);
}

