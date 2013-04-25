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

class Route
{
  public $source;
  public $args;
  public $callback;

  function __construct($source, $args, $callback)
  {
    $this->source = $source;
    $this->args = $args;
    $this->callback = $callback;
  }
}

class Controller
{
  private $routes;
  protected $user;
  protected $returnValue;

  function __construct()
  {
    $this->user = null;
    $this->returnValue = null;
    $this->routes = array();
    
    date_default_timezone_set(TIMEZONE);
  }

  function onStarted()
  {
    $this->user = User::getCurrent();
  }

  function onInitialized()
  {
  }

  function getCurrentUser()
  {
    return $this->user;
  }

  // Return values: 
  //   false: unauthenticated users OK
  //    true: must be authenticated
  //  <role>: must be authenticated AND authorized
  function mustBeAuthorized()
  {
    return true;
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

  function unsetUser()
  {
    $this->user = false;
  }

  function initRoutes()
  {
  }
  
  function addRoute($source, $args, $callback)
  {
    $this->routes[] = new Route($source, $args, $callback);
  }

  function addGetRoute($args, $callback)
  {
    $this->addRoute("get", $args, $callback);
  }

  function addPostRoute($args, $callback)
  {
    $this->addRoute("post", $args, $callback);
  }

  function addFileRoute($args, $callback)
  {
    $this->addRoute("file", $args, $callback);
  }

  function defaultRoute()
  {
  }

  function redirectTo($url)
  {
    header('Location: '.$url);
  }

  function route()
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

        break;
      }
    }
    
    if ($controllerCallback == null)
      return $this->defaultRoute();
    else
    {
      if (!is_callable($controllerCallback))
        throw new Exception("Route callback not valid");

      return call_user_func_array($controllerCallback, $controllerCallbackArgs);
    }
  }

  function reauthenticate()
  {
    $this->redirectTo("login.php");
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
    }
    catch(Exception $e)
    {
      $this->onError($e);
    }
  }

  function onError($e)
  {
    throw $e;
  }

  function getStorage()
  {
    return Storage::getInstance();
  }
}

?>