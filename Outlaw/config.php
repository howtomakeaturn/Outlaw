<?php

$config['database'] = array(
    'dsn' => 'mysql:host=localhost;dbname=koala',
    'user' => 'koala',
    'password' => 'koala'
);

/*
 * Validation rules.
 * Powered by Valitron:
 * https://github.com/vlucas/valitron
 */
$config['rules'] = array(

    // Some examples
    /*
    'articles' => array(
        // notice the attribute is wrapped in an array even it's just a string
        'required' => [['ol_title'], ['ol_content']],
        'lengthMin' => [['ol_title', 5], ['ol_content', 10]]
    ),
    'stores' => [
        'required' => [ ['ol_name'], ['ol_boss'], ['ol_phone'], ['ol_address'] ]
    ],
    'products' => array(
        // notice the attribute is wrapped in an array even it's just a string
        'required' => [['ol_title'], ['ol_content']],
        'lengthMin' => [['ol_title', 5], ['ol_content', 10]]
    ),
    */
);

/*
 * Language used in validation.
 */
$config['lang'] = 'en';       

/*
 * Path to store the uploaded files.
 */                
$config['upload_path'] = './upload/';             
        
