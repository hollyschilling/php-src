--TEST--
Generics: generic traits stamp per use-site with substituted method and property types
--FILE--
<?php
trait Cache<T> {
    private ?T $cached = null;
    public function remember(T $value): T { return $this->cached = $value; }
    public function cached(): ?T { return $this->cached; }
}

class UserRepo { use Cache<DateTime>; }
class IntRepo  { use Cache<int>; }

$u = new UserRepo();
$u->remember(new DateTime());
echo get_class($u->cached()), "\n";
try { $u->remember(42); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

$i = new IntRepo();
var_dump($i->remember(7));
try { $i->remember(new DateTime()); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

try { (function() { $this->cached = "nope"; })->call($i); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

var_dump((string) (new ReflectionProperty('IntRepo', 'cached'))->getType());
?>
--EXPECTF--
DateTime
UserRepo::remember(): Argument #1 ($value) must be of type DateTime, int given, called in %s on line %d
int(7)
IntRepo::remember(): Argument #1 ($value) must be of type int, DateTime given, called in %s on line %d
Cannot assign string to property IntRepo::$cached of type ?int
string(4) "?int"
