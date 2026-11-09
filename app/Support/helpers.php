<?php

if (! function_exists('rupiah')) {
    function rupiah(int|float|null $amount, bool $prefix = true): string
    {
        return ($prefix ? 'Rp' : '').number_format((float) $amount, 0, ',', '.');
    }
}
