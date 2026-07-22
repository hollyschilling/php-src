--TEST--
Generics: param-dependent extends — catchable stamp errors: final/interface parents, missing parent, cycles
--FILE--
<?php

final class FP<T> {}
class FC<T> extends FP<T> {}
try { new FC<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

interface IP<T> {}
class IPC<T> extends IP<T> {}
try { new IPC<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

class NF<T> extends Missing<T> {}
try { new NF<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

class NG { }
class NGC<T> extends NG<T> {}
try { new NGC<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

class CY<T> extends CY<T> {}
try { new CY<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

class MA<T> extends MB<T> {}
class MB<T> extends MA<T> {}
try { new MA<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
Cannot stamp FC<int>: cannot extend final class FP<int>
Cannot stamp IPC<int>: cannot extend interface IP<int>
Cannot stamp NF<int>: parent class Missing<int> was not found
Class NG is not generic
Circular generic instantiation involving CY<int>
Circular generic instantiation involving MA<int>
