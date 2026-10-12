<?php

use Illuminate\Support\Facades\Schema;

it('creates every table with InnoDB, whatever the server default engine is', function () {
    expect(config('database.connections.mysql.engine'))->toBe('InnoDB');

    $engines = collect(Schema::getTables(Schema::getCurrentSchemaName()))->pluck('engine', 'name');

    expect($engines)->not->toBeEmpty()
        ->and($engines->unique()->values()->all())->toBe(['InnoDB']);
});
