--TEST--
Generics: using a stamped generic trait keeps sole ownership of substituted arg_info
--FILE--
<?php
declare(strict_types=1);
// `use Cache<int>` binds methods by memcpy'ing the trait instantiation's
// op_array headers (zend_add_trait_method). The source method carries a
// substituted arg_info block and its ownership flag; the copy must not share
// them, or teardown releases the same strings and types twice.

trait Cache<T> {
    // The doc comment is a non-interned heap string owned by the substituted
    // block; a union with T substitutes to a heap-allocated type list.
    public function remember(/** the value */ T|string $v): ?T {
        return is_string($v) ? null : $v;
    }

    // Hooks are copied by the same header-memcpy as methods
    // (zend_traits_copy_properties) and need the same ownership handoff.
    public T|string $latest = "none" {
        set(T|string $x) { $this->latest = $x; }
    }
}

class IntRepo { use Cache<int>; }
class FloatRepo { use Cache<float>; }

$i = new IntRepo();
var_dump($i->remember(7));
var_dump($i->remember("s"));
try { $i->remember(1.5); } catch (TypeError $e) { echo "enforced\n"; }

$f = new FloatRepo();
var_dump($f->remember(2.5));

var_dump((string) (new ReflectionMethod('IntRepo', 'remember'))->getParameters()[0]->getType());
var_dump((string) (new ReflectionMethod('Cache<int>', 'remember'))->getParameters()[0]->getType());

$i->latest = 5;
var_dump($i->latest);
try { $i->latest = 1.5; } catch (TypeError $e) { echo "hook enforced\n"; }

echo "ok\n";
?>
--EXPECT--
int(7)
NULL
enforced
float(2.5)
string(10) "string|int"
string(10) "string|int"
int(5)
hook enforced
ok
