--TEST--
Poisoned shapes fall back to plain '<' outside parser mode (token_get_all never throws)
--EXTENSIONS--
tokenizer
--FILE--
<?php
// The ambiguity poison is a PARSER_MODE compile error; the tokenizer must
// keep lexing old code as comparisons.
$toks = token_get_all('<?php $o->m<A, B>($x);');
$names = [];
foreach ($toks as $t) {
    if (is_array($t)) { $names[] = token_name($t[0]); }
    else { $names[] = $t; }
}
echo implode(' ', array_slice($names, 1)), "\n";
var_dump(in_array('T_GENERIC_OPEN', $names, true));
var_dump(in_array('T_ERROR', $names, true));
?>
--EXPECT--
T_VARIABLE T_OBJECT_OPERATOR T_STRING < T_STRING , T_WHITESPACE T_STRING > ( T_VARIABLE ) ;
bool(false)
bool(false)
