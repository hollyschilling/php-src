--TEST--
Surfaces: enums may declare surfaces
--FILE--
<?php
enum Suit: string {
    surface Meta;

    case Hearts = 'h';
    case Spades = 's';

    surface[Meta] function code(): string { return $this->value . '!'; }
}

try { Suit::Hearts->code(); } catch (Error $e) { echo "denied\n"; }

function meta(Suit $s): void {
    use Suit with surface[Meta];
    echo $s->code(), "\n";
}
meta(Suit::Spades);
?>
--EXPECT--
denied
s!
