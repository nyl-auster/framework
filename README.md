PHP fucking deadly simple light procedural framework.

REQUIREMENTS
-------------

* php >= 5.4
* Apache

INSTALLATION
-------------

* Copy "www/example.index.php" to "www/index.php" file (you may name it as you want or create several entry points, like index_fr.php)
* Copy "example.config" directory to "config"
* Rename "example.htaccess" to ".htaccess"

Edit pages.php file to start create pages on your site.

ROUTES.PHP
--------------

Routes.php file map a framework path to a php callable.
A route may return a string or a closure :
In config/routes.php :

```php
$routes['homepage'] = [
    'path' => '',
    'content' => 'hello i am the homepage',
];
// to render a template page.php inside a layout.php template
$routes['homepage'] = [
    'path'   => '',
    'template' => 'layout.php',
    'content' =>  template('homepage.php')
];
$routes['hello'] => [
    'path' => 'hello',
    'content' => function() {
      $controller = new \myVendor\myModule\myController();
      $controller->hello();
    }
  ]
return $routes;
```

TEMPLATE
---------------

Use a template to render a page with variables :
```php
template('path/to/template.php', ['variable' => 'value'])
```

Printing in a secured way a variable in a template :
Never use print or echo to avoid code injection.
```php
<?php e($variable) ?>
```

Use a function to format a value
```php
<?php e($prix, 'euros') ?>
```