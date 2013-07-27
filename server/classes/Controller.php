<?
/*****************************************************************************
 **
 ** grr >:(
 ** https://github.com/melllvar/grr
 ** Copyright (C) 2013 Akop Karapetyan
 **
 ** This program is free software; you can redistribute it and/or modify
 ** it under the terms of the GNU General Public License as published by
 ** the Free Software Foundation; either version 2 of the License, or
 ** (at your option) any later version.
 **
 ** This program is distributed in the hope that it will be useful,
 ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 ** GNU General Public License for more details.
 **
 ** You should have received a copy of the GNU General Public License
 ** along with this program; if not, write to the Free Software
 ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 **
 ******************************************************************************
 */

require('classes/View.php');

class Controller
{
  private $routes;
  protected $user;
  protected $returnValue;
  protected $hasher;
  protected $routeTemplate;
  protected $scriptName;
  protected $undefined;
  protected $currentLocale;

  function __construct()
  {
    $this->user = null;
    $this->hasher = null;
    $this->returnValue = null;
    $this->routes = array();
    $this->routeTemplate = null;
    $this->undefined = array();
    $this->scriptName = null;
    $this->currentLocale = $GLOBALS['grrCurrentLocale'];

    date_default_timezone_set(TIMEZONE);
  }

  public function getCurrentLocale()
  {
    return $this->currentLocale;
  }

  public function isInDefaultLocale()
  {
    return $this->currentLocale == null;
  }

  function __get($name)
  {
    if (array_key_exists($name, $this->undefined))
      return $this->undefined[$name];

    return null;
  }

  function __set($name, $value)
  {
    $this->undefined[$name] = $value;
  }

  // Public methods

  public function url($controller, $args = array())
  {
    $args["c"] = $controller;
    $queryString = http_build_query($args);

    $rootUri = "/";
    $currentUri = $_SERVER['REQUEST_URI'];

    if (strlen($currentUri) > 0)
    {
      $rootUri = preg_replace('/\\/[^\\/]+$/', '/', $currentUri);
      if (substr($rootUri, 0, 1) == '/')
        $rootUri = substr($rootUri, 1);
    }

    $proto = "http";
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
      $proto = "https";
    
    $port = $_SERVER['SERVER_PORT'];
    $port = ( ( $port == '80' && $proto == 'http' ) || ( $port == '443' && $proto == 'https' ) ? '' : ':'.$port );

    return "{$proto}://{$_SERVER['SERVER_NAME']}{$port}/{$rootUri}?{$queryString}";
  }

  function getCurrentUser()
  {
    return $this->user;
  }

  function isAuthenticated()
  {
    return $this->user instanceof User;
  }

  function isAuthorized()
  {
    $authorization = $this->mustBeAuthorized();
    if ($authorization === false)
      return true;

    // Must be authenticated past this point
    if (!($this->user instanceof User))
      return false;

    if ($authorization === true)
      return true; // Authenticated

    if ($this->user->role === ROLE_ADMIN)
      return true; // Admins authorized for any role

    return $this->user->role === $authorization;
  }

  function isValidEmailAddress($emailAddress)
  {
    if (!$emailAddress)
      return false;

    return preg_match('/^[^@\\s]+@[^@\\s]+\\.[^@\\s\\.]{2,}$/', $emailAddress);
  }

  function getTemplate()
  {
    return $this->routeTemplate;
  }

  function getScriptName()
  {
    return $this->scriptName;
  }

  function setScriptName($scriptName)
  {
    $this->scriptName = $scriptName;
  }

  function execute()
  {
    try
    {
      $this->onStarted();

      if (!$this->isAuthorized())
      {
        $this->reauthenticate();
        return;
      }

      $this->onInitialized();

      $this->initRoutes();
      $this->returnValue = $this->route();

      if (!headers_sent())
      {
        $this->onPreRender();
        $this->renderView();
      }
    }
    catch(Exception $e)
    {
      $this->onError($e);
    }
  }

  function getStorage()
  {
    return Storage::getInstance();
  }

  // Events

  protected function onStarted()
  {
    $this->user = false;

    // Get current user

    if (isset($_COOKIE[COOKIE_AUTH]) && isset($_COOKIE[COOKIE_VAUTH]))
    {
      $hash = $_COOKIE[COOKIE_AUTH];
      $receivedVHash = $_COOKIE[COOKIE_VAUTH];

      $storage = Storage::getInstance();
      $this->user = $storage->getUserWithSessionHash($hash, $receivedVHash);
    }
  }

  protected function onInitialized()
  {
  }

  protected function onError($e)
  {
    throw $e;
  }

  protected function onPreRender()
  {
  }

  // Protected methods

  protected function getHasher()
  {
    if (!$this->hasher)
      $this->hasher = new PasswordHash(8, FALSE);

    return $this->hasher;
  }

  // Return values: 
  //   false: unauthenticated users OK
  //    true: must be authenticated
  //  <role>: must be authenticated AND authorized
  protected function mustBeAuthorized()
  {
    return true;
  }

  protected function initRoutes()
  {
  }
  
  protected function addGetRoute($args, $callback, $template = null)
  {
    $this->addRoute("get", $args, $callback, $template);
  }

  protected function addPostRoute($args, $callback, $template = null)
  {
    $this->addRoute("post", $args, $callback, $template);
  }

  protected function addFileRoute($args, $callback, $template = null)
  {
    $this->addRoute("file", $args, $callback, $template);
  }

  protected function defaultRoute()
  {
  }

  protected function setTemplate($template)
  {
    $this->routeTemplate = $template;
  }

  protected function route()
  {
    $controllerCallback = null;
    $controllerCallbackArgs = null;

    foreach ($this->routes as $route)
    {
      $source = null;
      if ($route->source == "get")
        $source = $_GET;
      else if ($route->source == "post")
        $source = $_POST;
      else if ($route->source == "file")
        $source = $_FILES;
      else
        throw new Exception("Unrecognized route source: {$route->source}");

      $match = true;
      $callbackArgs = array();

      foreach ($route->args as $arg)
      {
        if (!isset($source[$arg]))
        {
          $match = false;
          break;
        }

        $callbackArgs[] = $source[$arg];
      }

      if ($match)
      {
        $controllerCallback = array($this, $route->callback);
        $controllerCallbackArgs = $callbackArgs;

        // Set the name of the route template
        $this->routeTemplate = $route->template;

        // Strip 'route' from the end, if present
        if (stripos(strrev($this->routeTemplate), "etuor") === 0)
          $this->routeTemplate = substr($this->routeTemplate, 0, -5);

        break;
      }
    }
    
    if ($controllerCallback == null)
    {
      $this->routeTemplate = "default";
      return $this->defaultRoute();
    }
    else
    {
      if (!is_callable($controllerCallback))
        throw new Exception("Route callback not valid");

      return call_user_func_array($controllerCallback, $controllerCallbackArgs);
    }
  }

  protected function redirectTo($controller, $args = null)
  {
    header("Location: {$this->url($controller, $args)}");
  }

  protected function redirectToUrl($url)
  {
    header("Location: $url");
  }

  protected function reauthenticate()
  {
    $this->redirectTo("login");
  }

  protected function renderView()
  {
    if ($this->getTemplate())
    {
      $view = new View($this);
      $view->render();
    }
  }

  // Private methods

  private function addRoute($source, $args, $callback, $template)
  {
    $route = new stdClass();

    $route->source = $source;
    $route->args = $args;
    $route->callback = $callback;
    $route->template = $template ? $template : $callback;

    $this->routes[] = $route;
  }
}

?>
