--TEST--
Surfaces: surface-gated construction
--FILE--
<?php
final class User {
    surface Creation;

    surface[Creation] function __construct(public readonly string $name) {}

    public static function make(string $n): User {
        return new self($n); // the class holds its own surfaces
    }
}

try { $u = new User("Ada"); } catch (Error $e) { echo $e->getMessage(), "\n"; }

$u = User::make("Ada");
echo $u->name, "\n";

final class UserFactory {
    public function fromName(string $n): User {
        use User with surface[Creation];
        return new User($n);
    }
}
echo (new UserFactory)->fromName("Grace")->name, "\n";
?>
--EXPECT--
Call to surface constructor User::__construct() (grant it with "use User with surface[...]")
Ada
Grace
