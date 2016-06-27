<?php namespace Novica89\Erply;

use Illuminate\Support\Facades\Facade;

class ErplyClient extends Facade {

    protected static function getFacadeAccessor() { return 'novicaErply'; }

}