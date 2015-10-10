<?php

namespace Ornament;

use PDO as Base;

trait Pdo
{
    use Storage;
    use Identify;

    public function addPdoAdapter(Base $pdo, $id = null, array $fields = null)
    {
        if (!isset($id)) {
            $annotations = $this->annotations()['class'];
            $id = isset($annotations['Identifier']) ?
                $annotations['Identifier'] :
                $this->guessIdentifier();
        }
        $annotations = $this->annotations()['properties'];
        if (!isset($fields)) {
            $fields = [];
            foreach ($this->properties() as $prop) {
                if (property_exists($this, $prop)
                    && !isset($annotations[$prop]['Virtual'])
                    && !is_array($this->$prop)
                ) {
                    $fields[] = $prop;
                }
            }
        }
        $pk = [];
        foreach ($annotations as $prop => $anno) {
            if (isset($anno['PrimaryKey'])) {
                $pk[] = $prop;
            }
        }
        if (!$pk && in_array('id', $fields)) {
            $pk[] = 'id';
        }
        $adapter = new Adapter\Pdo($pdo, $id, $fields);
        if ($pk) {
            call_user_func_array([$adapter, 'setPrimaryKey'], $pk);
        }
        return $this->addAdapter($adapter, $id, $fields);
    }
}

