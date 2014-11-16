<?php

$pages = [];

require "okc/content/controllers/contentController.php";
require_once 'okc/content/api/contentApi.php';

// list all site contents
$pages['okc.content.list'] = [
  'path' => 'admin/content',
  'layout' => 'adminPage.php',
  'content' => function() {
      return contentListPage();
    }
];

// display an add / edit content form
$pages['okc.content.form'] = [
  'path' => 'admin/content/form',
  'layout' => 'adminPage.php',
  'layout_variables' => [
    'head' => function() {
        return '<script src="//cdn.ckeditor.com/4.4.5.1/standard/ckeditor.js"></script>';
      },
  ],
  'content' => function() {
      return contentFormPage();
    }
];

// save content to database
$pages['okc.content.form.save'] = [
  'path' => 'admin/content/form/save',
  'layout' => 'adminPage.php',
  'content' => function () {
      return contentFormSavePage();
    }
];

return $pages;