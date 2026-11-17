<?php

arch('no debugging calls are left in app code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('env() is read only in config files')
    ->expect('env')
    ->not->toBeUsedIn('App');
