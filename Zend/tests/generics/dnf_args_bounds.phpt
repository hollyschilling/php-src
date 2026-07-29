--TEST--
Composite (DNF) arguments against bounds: unions need every member, intersections one
--FILE--
<?php declare(strict_types=1);
interface Marked {}
class M1 implements Marked {}
class M2 implements Marked {}
class Plain {}
interface Extra {}
class ME implements Marked, Extra {}

class Keep<T implements Marked> { public function __construct() {} }

// union argument: every member satisfies the bound
var_dump((new Keep<M1|M2>())::class);

// union with a non-satisfying member fails
try { $c = 'Keep<M1|Plain>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// union with a builtin member fails a class bound
try { $c = 'Keep<M1|int>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// intersection argument: one satisfying member suffices
var_dump((new Keep<Extra&M1>())::class);

// null member never satisfies a bound
try { $c = 'Keep<M1|null>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
string(11) "Keep<M1|M2>"
%s does not satisfy the bound Marked of type parameter T on Keep
%s does not satisfy the bound Marked of type parameter T on Keep
string(14) "Keep<Extra&M1>"
%s does not satisfy the bound Marked of type parameter T on Keep
