<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scraper API Token
    |--------------------------------------------------------------------------
    | Token usado por los scrapers para autenticarse contra el ERP.
    | Debe enviarse en el header X-SCRAPER-TOKEN
    |
    */

    'token' => env('SCRAPER_TOKEN'),
];
