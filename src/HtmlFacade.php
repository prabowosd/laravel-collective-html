<?php

namespace Collective\Html;

use Illuminate\Support\Facades\Facade;

/**
 * @see HtmlBuilder
 */
class HtmlFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'html';
    }
}
