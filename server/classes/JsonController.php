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

class JsonError extends Exception
{
}

class JsonController extends Controller
{
  function reauthenticate()
  {
    throw new JsonError(l("Your session has timed out. Please sign in."), ERROR_REAUTHENTICATE);
  }

  private function echoJson($array)
  {
    header('Content-type: application/json');
    echo json_encode($array);
  }

  function onError($e)
  {
    $message = null;
    $code = 0;

    if ($e instanceof JsonError)
    {
      $message = $e->getMessage();
      $code = $e->getCode();
    }
    else
    {
      $message = l("An unexpected error has occurred");
    }

    $error = array("message" => $message);
    if ($code != 0) 
      $error["code"] = $code;

    $this->echoJson(array(
      "error"   => $error,
    ));
  }

  function execute()
  {
    parent::execute();

    if ($this->returnValue !== null)
      $this->echoJson($this->returnValue);
  }
}
?>