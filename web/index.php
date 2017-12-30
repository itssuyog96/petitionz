<?php

require('../vendor/autoload.php');

// Using Medoo namespace
use Medoo\Medoo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Initialize

$app = new Silex\Application();
$app['debug'] = true;

// $app['db'] = new Medoo([
//   'database_type' => 'mysql',
//   'database_name' => 'q5r6zfzoeqsnvp5q',
//   'server' => 'vvfv20el7sb2enn3.cbetxkdyhwsb.us-east-1.rds.amazonaws.com',
//   'username' => 'q7v7t2hzkho69580',
//   'password' => 'fifpiwbyfv1gc6xi'
// ]);

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Our web handlers

$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  // $data = $app['db']->select('aadhartbl', ['firstname', 'aadharno'], ['aadharno' => '123456789111']);

  echo 'Fetched data : ' . json_encode($data);
  return $app['twig']->render('index.twig');
});

$app->get('/signup', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  return $app['twig']->render('signup.twig');
});

$app->post('/register', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');

  echo $request->get('firstname') . " : " . $request->get('aadhar') . "\n";
  return new Response('Values recieved', 200);
});

$app->run();
