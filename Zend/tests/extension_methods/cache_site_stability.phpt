--TEST--
Extension methods: call-site resolutions are per-site stable within a request
--FILE--
<?php
class P {}
class X extends P {}

extension P $p {
    public function who(): string { return "P-extension"; }
}

function site(X $x): string { return $x->who(); }

$x = new X();
var_dump(site($x));            // resolves via P's extension, cached at this site

// A more-derived target's extension loads later in the request.
eval(<<<'PHP'
extension X $x {
    public function who(): string { return "X-extension"; }
}
PHP);

// The already-resolved site keeps its answer (per-site stability)...
var_dump(site($x));
// ...while a fresh site sees the more-derived winner.
eval('function site2(X $x): string { return $x->who(); }');
var_dump(site2($x));
?>
--EXPECT--
string(11) "P-extension"
string(11) "P-extension"
string(11) "X-extension"
