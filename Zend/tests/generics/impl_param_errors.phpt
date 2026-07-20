--TEST--
Generics M4-lite: nested param args stay banned; extends stays deferred; cycles error cleanly
--FILE--
<?php
interface A<T> extends B<T> {}
interface B<T> extends A<T> {}
try { class_exists("A<int>"); } catch (Error $e) { echo $e->getMessage(), "\n"; }

class Vec<T> {}
try {
    eval("class BadNested<T> implements ArrayAccess<Vec<T>> {}");
} catch (Error $e) { echo "unreached\n"; }
?>
--EXPECTF--
Circular generic instantiation involving A<int>

Fatal error: Cannot use type parameter T as a generic type argument (type arguments must be concrete in this version) in %s(%d) : eval()'d code on line %d
