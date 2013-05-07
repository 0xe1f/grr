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

abstract class Router
{
  private $routes;
  private $defaultControllerId;

  function __construct()
  {
    $this->routes = array();
    $this->defaultControllerId = null;
  }

  private function getControllerPath($controllerId)
  {
    $appRoot = realpath(dirname(dirname(__FILE__)));

    return "$appRoot/controllers/".ucwords($controllerId)."Controller.php";
  }

  private function getControllerClass($controllerId)
  {
    return ucwords($controllerId)."Controller";
  }

  private function do404()
  {
    header('HTTP/1.0 404 Not Found');
?>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
  <html>
    <head>
      <title>404 Not Found</title>
    </head>
    <body>
      <h1>Not Found</h1>
      <p>The requested URL was not found on this server.</p>
    </body>
  </html>
<?
  }

  protected function addRoute($id, $className, $isDefault = false)
  {
    $this->routes[$id] = $className;
    if ($isDefault)
      $this->defaultControllerId = $id;
  }

  protected abstract function initRoutes();

  private function route()
  {
    $controllerId = $_GET["c"];
    if (!$controllerId)
      $controllerId = $this->defaultControllerId;

    $controller = $this->routes[$controllerId];
    if (!$controller)
    {
      $this->do404();
      return;
    }

    $controllerPath = $this->getControllerPath($controllerId);
    $controllerClass = $this->getControllerClass($controllerId);

    include($controllerPath);

    $controller = @new $controllerClass();
    if (!($controller instanceof Controller))
    {
      $this->do404();
      return;
    }

    $controller->setScriptName($controllerId);
    $controller->execute();
  }

  public function start()
  {
    $this->initRoutes();
    $this->route();
  }
}

?>