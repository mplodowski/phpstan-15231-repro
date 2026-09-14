<?php

namespace Demo\Analysis;

class Alpha
{
    public function label(): string
    {
        return (new Zeta)->name();
    }
}
