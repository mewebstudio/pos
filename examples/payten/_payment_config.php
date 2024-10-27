<?php

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/payten';
$posClass = \Mews\Pos\Gateways\EstV3Pos::class;

$testCards = [
    'visa2' => [
        'number' => '4355084355084358',
        'year' => '30',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
    ],
    'visaZiraat' => [
        'number' => '4546711234567894',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
    ],
    'masterZiraat' => [
        'number' => '5401341234567891',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
    ],
    'visa1' => [
        'number' => '4546711234567894',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
    ],
    'visa_isbank_imece' => [
        /**
         * IMECE kartlar isbankin tarima destek icin ozel kampanyalari olan kartlardir.
         * @link https://www.isbank.com.tr/is-ticari/imece-kart
         *
         * bu karti test edebilmek icin bu kartlarla odemeyi destekleyen Isbank Pos hesabi lazim.
         */
        'number' => '4242424242424242',
        'year'   => '2028',
        'month'  => '10',
        'cvv'    => '123',
        'name'   => 'John Doe',
    ],
];
