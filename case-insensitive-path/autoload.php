<?php

spl_autoload_register(function (string $class): void {
    if ($class === 'Demo\\Analysis\\Zeta') {
        require __DIR__ . '/src/analysis/Zeta.php';
    }
});
