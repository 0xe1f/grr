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

class View
{
  private $user;
  private $controller;
  
  function __construct($controller)
  {
    $this->controller = $controller;
    $this->user = $controller->getCurrentUser();
  }

  private function getTemplatePath()
  {
    $appRoot = realpath(dirname(dirname(__FILE__)));

    return "$appRoot/views/{$this->controller->getScriptName()}/{$this->controller->getTemplate()}.html";
  }

  private function getPartialPath($partial)
  {
    $appRoot = realpath(dirname(dirname(__FILE__)));

    return "$appRoot/views/{$this->controller->getScriptName()}/partials/{$partial}.html";
  }

  function __get($name)
  {
    // Access a variable in the controller 
    
    return $this->controller->$name;
  }

  function __call($name, $arguments)
  {
    // Call a method in the controller

    $method = array($this->controller, $name);
    return call_user_func_array($method, $arguments);
  }

  public function render()
  {
    @include($this->getTemplatePath());
  }

  protected function renderPartial($partialName, $args = null)
  {
    if ($args)
      foreach ($args as $argName => $argValue)
        $$argName = $argValue;

    @include($this->getPartialPath($partialName));
  }
}
?>