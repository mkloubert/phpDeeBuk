<?php

namespace MyNamespace;

?>
<html>
<body>
<?php

require_once "./phpDeeBuk.php";

class MyClass {
    public $C = 12;
    public $B;
    private $_a;

    const ABC = 'TM';

    public function a() {

    }

    private function b() {

    }
}

$dbg = \phpDeeBuk::getInstance();

$a = 1000;

$o = new MyClass();
$o->B = 5979;

$dbg->writeLine('Hello')
    ->write('world!')
    ->setForegroundColor('ff0')
    ->setUnderline()->writeFormat(' %s %s', 1, 2)->setUnderline(false)
    ->resetColors()
    ->writeLine()
    ->write('TM')
    ->analyze($a, 'testValue')
    ->analyze($o)
    ->dump($o);

$dbg->renderAndOutput();


?>
</body>
</html>