--TEST--
Generics: closures declared inside a template get substituted signatures at creation
--FILE--
<?php
class Vec<T> {
    private array $items = [];
    public function push(T $x): void { $this->items[] = $x; }
    public function validator(): Closure {
        return function (T $x): T { return $x; };
    }
    public function arrow(): Closure {
        return fn (T $x): ?T => $x;
    }
    public function statik(): Closure {
        return static function (T $x): void {};
    }
}

$i = new Vec<int>();
$c = $i->validator();
var_dump($c(42));
var_dump((string) (new ReflectionFunction($c))->getParameters()[0]->getType());
var_dump((string) (new ReflectionFunction($c))->getReturnType());
try { $c([]); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// independent per instantiation
$s = new Vec<string>();
$cs = $s->arrow();
var_dump($cs("hi"));
try { $cs([]); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// static closures substitute too
try { ($i->statik())([]); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// first-class callable over a stamped method: already substituted, no-op path
$fcc = $i->push(...);
var_dump((string) (new ReflectionFunction($fcc))->getParameters()[0]->getType());
try { $fcc([]); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
int(42)
string(3) "int"
string(3) "int"
Vec<int>::{closure:%s}(): Argument #1 ($x) must be of type int, array given, called in %s on line %d
string(2) "hi"
Vec<string>::{closure:%s}(): Argument #1 ($x) must be of type string, array given, called in %s on line %d
Vec<int>::{closure:%s}(): Argument #1 ($x) must be of type int, array given, called in %s on line %d
string(3) "int"
Vec<int>::push(): Argument #1 ($x) must be of type int, array given, called in %s on line %d
