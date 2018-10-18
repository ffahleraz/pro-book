<?php
class Router {
  private $request;
  private $supportedHttpMethods = array(
    "GET",
    "POST"
  );
  private $routes;

  function __construct(RequestInterface $req) {
    $this->request = $req;
  }

  function get(string $route, Closure $callback, $middlewares = null) {
    $method = 'GET';
    if(!in_array(strtoupper($method), $this->supportedHttpMethods)) {
      $this->invalidMethodHandler();
      return;
    }

    $this->routes[strtoupper($method)][$this->formatRoute($route)] = array(
      'callback' => $callback,
      'middlewares' => $middlewares
    );
  }

  function post(string $route, Closure $callback, $middlewares = null) {
    $method = 'POST';
      if(!in_array(strtoupper($method), $this->supportedHttpMethods)) {
        $this->invalidMethodHandler();
        return;
      }

      $this->routes[strtoupper($method)][$this->formatRoute($route)] = array(
        'callback' => $callback,
        'middlewares' => $middlewares
      );
  }

  function __call(string $method, $args) {
      list($route, $callback, $middlewares) = $args;
      if(!in_array(strtoupper($method), $this->supportedHttpMethods)) {
        $this->invalidMethodHandler();
      }
      $this->routes[strtoupper($method)][$this->formatRoute($route)] = array(
        'callback' => $callback,
        'middlewares' => $middlewares
      );
  }

  /**
   * Removes trailing forward slashes from the right of the route.
   * @param route (string)
   */
  private function formatRoute(string $route) {
    $result = rtrim($route, '/');
    if ($result === '') {
      return '/';
    }
    return $result;
  }

  private function invalidMethodHandler() {
    header("{$this->request->serverProtocol} 405 Method Not Allowed");
    // TODO: Add page
  }

  private function defaultRequestHandler() {
    header("{$this->request->serverProtocol} 404 Not Found");
    // TODO: Add page
  }

  private function matchRoute(string $uri, string $requestMethod) {
    foreach($this->routes[$requestMethod] as $route => $v) {
      if(strcmp($uri, $route) == 0) {
        return $v;
      }
    }
    return null;
  }

  private function importGetVariables() {
    foreach($_GET as $key => $value) {
      $this->request->{Router::toCamelCase($key)} = $value;
    }
  }

  private function importPostVariable() {
    foreach($_POST as $key => $value) {
      $this->request->{Router::toCamelCase($key)} = $value;
    }
  }

  private static function toCamelCase($string) {
    $result = strtolower($string);

    preg_match_all('/_[a-z]/', $result, $matches);
    foreach($matches[0] as $match) {
      $c = str_replace('_', '', strtoupper($match));
      $result = str_replace($match, $c, $result);
    }
    return $result;
  }

  /**
   * Resolves a route
   */
  function route() {
    $requestMethod = strtoupper($this->request->requestMethod);
    if ($requestMethod == 'GET') {
      $this->importGetVariables();
    } else if ($requestMethod == 'POST') { // POST
      $this->importPostVariable();
    } else {
      $this->invalidMethodHandler();
      return;
    }

    list('callback' => $callback, 'middlewares' => $middlewares) = $this->matchRoute(parse_url($this->request->requestUri)['path'], $requestMethod);

    if (is_null($callback)) {
      $this->defaultRequestHandler();
      return;
    }

    if (!is_null($middlewares)) {
      foreach($middlewares as $middleware) {
        if (!$middleware->run($this->request)) {
          exit();
        }
      }
    }

    echo $callback($this->request);
  }

  function __destruct() {
    $this->route();
  }
}