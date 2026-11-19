<?php

App\Models\Church::orderBy('id')->get()->each(function ($c): void {
    echo $c->id.'|'.$c->name.'|'.($c->parent_id ?? 'null').PHP_EOL;
});
