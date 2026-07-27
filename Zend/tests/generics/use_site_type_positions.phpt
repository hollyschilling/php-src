--TEST--
Generics M2: generic references in parameter, return, property and nullable type positions
--FILE--
<?php
class Vec<T> {}

class Repo {
    public ?Vec<int> $items = null;
    public function set(Vec<string> $v): Vec<Vec<int>> { throw new Error('unused'); }
}

var_dump((string) (new ReflectionProperty('Repo', 'items'))->getType());
$m = new ReflectionMethod('Repo', 'set');
var_dump((string) $m->getParameters()[0]->getType());
var_dump((string) $m->getReturnType());
?>
--EXPECT--
string(9) "?Vec<int>"
string(11) "Vec<string>"
string(13) "Vec<Vec<int>>"
