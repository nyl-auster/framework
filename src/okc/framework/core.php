<?php
/**
 * PHP framework core file.
 */

define("CONFIG_EXAMPLE_DIRECTORY", 'example.config');
define('CONFIG_DIRECTORY_PATH', '../config');
define('USER_CONTENT_DIRECTORY', 'user_content');
define('PAGES_FILEPATH', '../config/pages.php');
define('STRINGS_TRANSLATIONS_FILEPATH', '../config/translations.php');
define('SETTINGS_FILEPATH', '../config/settings.php');
define('SETTINGS_LOCAL_FILEPATH', '../config/settings.local.php');
define('TEMPLATE_FORMATTERS_FILEPATH', '../config/templateFormatters.php');

/**
 * Bootstrap the okc framework : listen http request and map it to
 * a php controller, looking at config/pages.php file.
 *
 * @param array $contextVariables : array of values to define site context
 * Use this bootstrap in a script in "www" directory with following example code :
 * @code
 * require_once "../src/okc/framework/bootstrap.php";
 * bootstrapFramework();
 * @endocde
 */
function bootstrapFramework($contextVariables = [])
{

  // Framework is not setup if "example.config" directory has not be renamed to "config".
  // Inform user and stop here for now.
  if (!file_exists(CONFIG_DIRECTORY_PATH)) {
    echo frameworkInstallationPage($contextVariables);
    exit;
  }

  // Add include paths and class autoloaders first.
  $includePaths = ['..', '../src', '../vendors'];
  addPhpIncludePaths($includePaths);
  setPsr0ClassAutoloader();

  // Connect to database specified in database settings if any, using PDO
  $database = getSetting("database");
  if (!empty($database)) {
    try {
      $db = new PDO("mysql:host={$database['host']};dbname={$database['name']}", $database['user'], $database['password']);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $contextVariables['db'] = $db;
    }
    catch (PDOException $e) {
      echo $e->getMessage();
      die();
    }
  }

  // Try to display framework logs even if php a fatal error occured
  register_shutdown_function("phpFatalErrorHandler");
  session_start();

  // register context variables in the site context
  setContextVariable('time_start', microtime(TRUE));
  foreach ($contextVariables as $key => $contextVariable)
  {
    setContextVariable($key, $contextVariable);
  }

  // include template formatters file, for template() function.
  require TEMPLATE_FORMATTERS_FILEPATH;

  // display page corresponding to submitted http request
  $controllerOutput = renderPageFromHttpRequest();
  echo $controllerOutput;

  writeLog(['level'    => 'notification', 'detail'   => "page content is '" . sanitizeString($controllerOutput) . "'"]);
  if (getSetting('display_developper_toolbar') === TRUE) require_once "../src/okc/framework/developperToolbar.php";
}

/**
 * Display information about framework setup to the user.
 * @return string html
 */
function frameworkInstallationPage() {
  $out = '';
  $out .= '<h1>Welcome to framework installation</h1>';
  $out .= 'Please rename "example.config" directory to "config" to start using framework.';
  return $out;
}

/**
 * Get all site Logs
 * @return array : all site logs
 */
function getAllLogs()
{
  return $GLOBALS['_LOGS'];
}

/**
 * Get database connexion to perform queries.
 * @return PDO connexion object
 */
function getDbConnexion() {
  return getContextVariable('db');
}

/**
 * Get current language used on the site by the visitor
 * @return string : langcode (fr, en etc...)
 */
function getCurrentLanguage()
{
  $currentLanguage = getSetting('language_default');
  if (isset($_REQUEST['language']))
  {
    $requestedLanguage = (string)sanitizeString($_REQUEST['language']);
    $definedLanguages = getSetting('languages');
    foreach ($definedLanguages as $id => $datas)
    {
      if ($definedLanguages[$id]['query'] == $requestedLanguage)
      {
        $currentLanguage = $requestedLanguage;
      }
    }
  }
  return $currentLanguage;
}

/**
 * Load all routes defined in files.
 * @return mixed
 */
function getAllPages()
{
  static $pages = [];
  if (!$pages) $pages = include PAGES_FILEPATH;
  return $pages;
}

/**
 * If there is several routes, last found route will be used.
 * @see config/pages.php
 * @param $path
 * @param $pages
 * @return array : page declaration
 */
function getPageDeclarationByPath($path, $pages)
{
  $page = [];
  foreach ($pages as $id => $datas)
  {
    if (isset($pages[$id]['path']) && $pages[$id]['path'] == $path)
    {
      $page = $pages[$id];
      $page['id'] = $id;
    }
  }
  return $page;
}

/**
 * Return a page by its key
 * @see config/pages.php file.
 * @param string $key
 * @return array : the page definition as an array
 */
function getPageDeclarationByKey($key) {
  $pages = getAllPages();
  return $pages[$key];
}

/**
 * Render a page content using its path.
 * @see config/pages.php file
 * @param string $path
 * @return string (html, json, xml or whatever the controller return to us.)
 */
function renderPageByPath($path) {
  $pages = getAllPages();
  $page = getPageDeclarationByPath($path, $pages);
  if (!$page) {
    $page = getPageDeclarationByKey('__PAGE_NOT_FOUND__', $pages);
  }
  $output = renderPage($page);
  return $output;
}

/**
 * Render a page listening to incoming http request
 * @see config/pages.php
 * @return string
 */
function renderPageFromHttpRequest()
{
  $scriptName = getSiteScriptName();
  writeLog(['level'  => 'notification', 'detail' => sprintf("Script name is %s", sanitizeString($scriptName))]);

  $basePath = getSiteBasePath($scriptName);
  writeLog(['level' => 'notification', 'detail' => sprintf("base path is %s", sanitizeString($basePath))]);
  setContextVariable('basePath', sanitizeString($basePath));

  $path = getPagePathFromHttpRequest($scriptName, $basePath);
  writeLog(['level'    => 'notification', 'detail'   => "Framework determine path from http request as '$path'"]);
  setContextVariable('path', sanitizeString($path));

  return renderPageByPath($path);
}

/**
 * Extract path from incoming http request.
 *
 * @param string $script_name as returned by getSiteScriptName()
 * @param string $base_path as returned by getSiteBasePath()
 * @return string
 */
function getPagePathFromHttpRequest($script_name, $base_path)
{
  // Remove base path (for installations in subdirectories) from URI.
  $pagePath = substr_replace($_SERVER['REQUEST_URI'], '', 0, strlen($base_path));
  // Remove scriptname "index.php" if present. scriptname is not present at all if .htaccess is enabled.
  if (strpos($pagePath, $script_name) === 0) $pagePath = str_replace($script_name, '', $pagePath);
  // remove query string and slashes from pagePath.
  return trim(parse_url($pagePath, PHP_URL_PATH), '/');
}

/**
 * Return value of a site settings
 * @see config/settings.php file.
 * @param string $key = settings identifier
 * @return mixed
 */
function getSetting($key)
{
  static $settings = [];
  if (!$settings) $settings = getAllSettings();
  return $settings[$key];
}

/**
 * Return all settings defined in config/settings.php file.
 * @return array
 */
function getAllSettings() {
  $siteSettings = include SETTINGS_FILEPATH;
  if (is_readable(SETTINGS_LOCAL_FILEPATH))
  {
    $siteSettingsLocal = include SETTINGS_LOCAL_FILEPATH;
    $siteSettings = array_merge($siteSettings, $siteSettingsLocal);
  }
  return $siteSettings;
}

/**
 * Return full context for the current framework response to the http request.
 * @return array
 */
function getContext()
{
  return $GLOBALS['_CONTEXT'];
}

/**
 * Get a context Variable by its key
 * @param string $key
 * @return mixed
 */
function getContextVariable($key)
{
  return $GLOBALS['_CONTEXT'][$key];
}

/**
 * Return base path, if framework is installed in a subfolder of the host
 *
 * @param string $scriptName as returned by getSiteScriptName()
 * @return string
 */
function getSiteBasePath($scriptName)
{
  return str_replace($scriptName, '', $_SERVER['SCRIPT_NAME']);
}

/**
 * file php which is the entry point of your application; usually "index.php".
 */
function getSiteScriptName()
{
  return basename($_SERVER['SCRIPT_NAME']);
}

/**
 * Get a translation for a specific string_id
 * @param $string_id
 * @param string $language
 * @return string : localized string
 */
function getTranslation($string_id, $language = NULL)
{
  static $translations = [];
  if (!$translations) $translations = include STRINGS_TRANSLATIONS_FILEPATH;
  if (!$language) $language = getCurrentLanguage();
  return $translations[$string_id][$language];
}

function url($path, array $options = [])
{
  $redirectionQuery = '';
  if (isset($options['redirection'])) {
    $redirectionQuery = 'redirection=' . urlencode($options['redirection']);
  }
  $scriptName = sanitizeString(getSiteScriptName());
  $url = getSiteBasePath($scriptName) . $scriptName . '/' . $path . '?' . $redirectionQuery;
  return $url;
}

/**
 * Return TRUE if $path is the current http requested path, FALSE otherwise.
 * Usefull to set "active" classes in html, for example for menus.
 * @param string $path
 * @return bool
 */
function isCurrentPath($path)
{
  $scriptName  = sanitizeString(getSiteScriptName());
  $basePath    = getSiteBasePath($scriptName);
  $urlPath     = getPagePathFromHttpRequest($scriptName, $basePath);
  return $path == $urlPath ? TRUE : FALSE;
}

/**
 * Render a page, parsing a page definition
 * @see config/pages.php
 * @param array $page : page array declaration as returned by getPageByPath() or getPageByKey()
 * @return bool
 */
function renderPage(array $page)
{
  $output = is_string($page['content']) ? $page['content'] : $page['content']();
  if (!empty($page['layout'])) {
    $layoutVariables = [];

    if (!empty($page['layout_variables'])) {
      $layoutVariables = $page['layout_variables'];
    }
    $layoutVariables['content'] = $output;
    $output = template($page['layout'], $layoutVariables);
  }
  return $output;
}

/**
 * Write a log
 *
 * @param array $log
 * associative array containing the following keys
 * - level : notice, warning, error
 * - detail : detail of the log
 */
function writeLog($log)
{
  $GLOBALS['_LOGS'][] = $log;
}

/**
 * Add an http header, using http or https given the curre
 * @param int $code : http response code 200, 400 etc...
 * @param $message : message associated to the http response code
 * @param $protocol (
 */
function addHttpResponseHeader($code, $message = null, $protocol = null) {
  $httpCodes = [
    200 => 'OK',
    403 => 'Forbidden',
    404 => 'Not Found',
  ];
  if (!$protocol)
  {
    $protocol = $_SERVER["SERVER_PROTOCOL"];
  }
  if (!$message)
  {
    if (!empty($httpCodes[$code]))
    {
      $message = $httpCodes[$code];
    }
    else
    {
      writeLog(['level' => 'warning', 'detail' => sprintf("No message found for http code %s", sanitizeString($code))]);
    }
  }
  header(sprintf("%s %s %s", sanitizeString($protocol), sanitizeString($code), sanitizeString($message)));
}

/**
 * Set or add a value to the context
 * @param $id
 * @param $value
 */
function setContextVariable($id, $value)
{
  $GLOBALS['_CONTEXT'][$id] = $value;
}

/**
 * Helper to merge settings from a file into settings of another file.
 * @param array $variable
 * @param string $filepath
 * @return array
 */
function mergeConfigFromFile($variable, $filepath)
{
  return $variable += require_once $filepath;
}

/**
 * Register a basic psr0 class autoloader.
 */
function setPsr0ClassAutoloader()
{
  spl_autoload_register(function($class){require_once str_replace('\\','/', $class).'.php';});
}

/**
 * @param array $include_paths
 *   a list of php path that will be add to php include paths
 */
function addPhpIncludePaths($include_paths)
{
  set_include_path(get_include_path() . PATH_SEPARATOR . implode(PATH_SEPARATOR, $include_paths));
}

/**
 * Sanitize a variable, to display it, encoding html.
 * @param string $value
 * @return string html encoded value
 */
function sanitizeString($value)
{
  return htmlspecialchars($value, ENT_QUOTES, 'utf-8');
}

function phpFatalErrorHandler()
{
  if(error_get_last() !== NULL) require "developperToolbar.php";
}

/**
 * Render a specific template file
 *
 * @param string $templatePath : file path
 * @param array $variables
 * @param string $directoryPath : theme to use. Different themes
 * may be defined. A theme is a collection of templates.
 * @return string
 */
function template($templatePath, $variables = [], $directoryPath = NULL)
{
  if (!$directoryPath)
  {
    $directoryPath = getSetting('theme_path');
  }
  if ($variables) extract($variables);
  ob_start();
  if (!is_readable($directoryPath . DIRECTORY_SEPARATOR . $templatePath))
  {
    writeLog(['level' => 'warning', 'detail' => sprintf("%s template is not readable or does not exist", sanitizeString($directoryPath . '/' . $templatePath))]);
  }
  include($directoryPath . '/' . $templatePath);
  return ob_get_clean();
}

/**
 * Echo a value in a secured /escaped way.
 * Do not use "print" or "echo" in templates when possible, as
 * this function take care of encoding malicious entities.
 *
 * @param string $value : a single string value to print.
 * @param array | string  $formatters : array of function names to format the value.
 * Special formatter "raw" may be used to disabled default escaping of the value.
 * @return string
 */
function e($value, $formatters = [])
{
  // sanitize value string by default unless "raw" special formatter name is requested
  $output = ($formatters != "raw" || !in_array('raw', $formatters)) ? $value : sanitizeString($value);

  if ($formatters) {
    // if formatters is a string, apply it directly and echo string :
    if (is_string($formatters))
    {
      $output = $formatters($output);
    }
    // if formatters is an array, apply each formatter to the string :
    else
    {
      foreach ($formatters as $function)
      {
        if ($function != "raw") $output = $function($output);
      }
    }
  }
  echo $output;
}

/**
 * @param string $machine_name : a string containing only alphanumeric and underscore characters
 * @return boolean : TRUE if machine_name is valid, FALSE otherwise
 */
function validateMachineName($machine_name)
{
  return (bool)preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $machine_name);
}

function getServerName() {
  return sanitizeString($_SERVER['SERVER_NAME']);
}

/**
 * FIXME : https & http detection here
 * @return string
 */
function getServerProtocol() {
  return 'http://';
}

function getFullDomainName() {
  return getServerProtocol() . getServerName();
}

function vde($value) {
  var_dump($value);exit;
}

function pre($array) {
  echo '<pre>';
  print_r($array);
  echo '</pre>';
  exit;
}

function setHttpRedirectionHeader($path) {
  $url = sanitizeString(url($path));
  $fullUrl = getFullDomainName() . $url;
  header("Location: $fullUrl");
}

function getHttpRedirectionFromUrl() {
  $path = null;
  if (!empty($_GET['redirection']))
  {
    $path = urldecode($_GET['redirection']);
  }
  return $path;
}

function setHttpRedirection($redirectionPath = NULL) {
  if (!$redirectionPath) {
    $redirectionPath = getHttpRedirectionFromUrl();
  }
  setHttpRedirectionHeader($redirectionPath);
  exit;
}

