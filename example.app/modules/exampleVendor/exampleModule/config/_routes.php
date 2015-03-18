<?php
/**
 * Define pages url and content.
 */

/**
 * Override default homepage
 */
$config['routes']['']['GET'] = [
  'template'    => 'exampleVendor/exampleModule/templates/page.php',
  'format'      => 'html',
  'controller'  =>  function() {
      return ['content' => "Ulysse works."];
    }
];

/**
 * Example route.
 *
 * visit localhost/{yoursite}/www/index.php/hello to view it.
 */
$config['routes']['hello']['GET'] = [
  'template'   => 'exampleVendor/exampleModule/templates/page.php',
  'format'     => 'html',
  'controller' => function() {
      return ['content' => "Hello world"];
    },
];

/**
 * Example route.
 *
 * visit localhost/{yoursite}/www/index.php/hello to view it.
 */
$config['routes']['hello/:name']['GET'] = [
  'arguments' => [':name'],
  'format'     => 'json',
  'controller' => function($name) {
      return ['message' => "Hello $name ! "];
    },
];



