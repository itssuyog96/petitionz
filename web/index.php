<?php
require('../vendor/autoload.php');

// Using Medoo namespace
use Medoo\Medoo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Google\Cloud\Language\LanguageClient;
use Google\Cloud\Core\ServiceBuilder;

putenv('GOOGLE_APPLICATION_CREDENTIALS=apis.google.com/key/Polar-oasis-6fe1e532496e.json');
/**
 * Find the sentiment in text.
 * ```
 * analyze_sentiment('Do you know the way to San Jose?');
 * ```
 *
 * @param string $text The text to analyze.
 * @param string $projectId (optional) Your Google Cloud Project ID
 *
 */
function analyze_sentiment($text, $projectId = 'polar-oasis')
{
    // Create the Natural Language client
    $language = new LanguageClient([
        'projectId' => $projectId,
    ]);

    // Call the analyzeSentiment function
    $annotation = $language->analyzeSentiment($text);

    // Print document and sentence sentiment information
    $sentiment = $annotation->sentiment();
    // printf('Document Sentiment:' . PHP_EOL);
    // printf('  Magnitude: %s' . PHP_EOL, $sentiment['magnitude']);
    // printf('  Score: %s' . PHP_EOL, $sentiment['score']);
    // printf(PHP_EOL);
    // foreach ($annotation->sentences() as $sentence) {
    //     printf('Sentence: %s' . PHP_EOL, $sentence['text']['content']);
    //     printf('Sentence Sentiment:' . PHP_EOL);
    //     printf('  Magnitude: %s' . PHP_EOL, $sentence['sentiment']['magnitude']);
    //     printf('  Score: %s' . PHP_EOL, $sentence['sentiment']['score']);
    //     printf(PHP_EOL);
    // }

    return array(
      'sentiment' => $sentiment,
      'annotation' => $annotation
    );
}

// Initialize
$app = new Silex\Application();
$app['debug'] = true;
$app['mail'] = new PHPMailer(true);                              // Passing `true` enables exceptions
$app['db'] = new Medoo([
  'database_type' => 'mysql',
  'database_name' => 'q5r6zfzoeqsnvp5q',
  'server' => 'vvfv20el7sb2enn3.cbetxkdyhwsb.us-east-1.rds.amazonaws.com',
  'username' => 'q7v7t2hzkho69580',
  'password' => 'fifpiwbyfv1gc6xi'
]);

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Register Session service
$app->register(new Silex\Provider\SessionServiceProvider());

// Our web handlers

if($app['session']->get('user')){
  $app['user'] = $app['session']->get('user');
  // echo "User id : ". json_encode($app['user']);
}

$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  return $app['twig']->render('index.twig', ['title' => 'Home']);
});

$app->get('/signup', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  if($app['session']->get('user')){
    return $app->redirect('/');
  }

  return $app['twig']->render('signup.twig', ['title'=> 'Sign Up']);
});

$app->get('/charts', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');

  if($app['session']->get('user')){
    return $app->redirect('/');
  }  
 
  

  return $app['twig']->render('charts.twig', ['title'=> 'Charts']);
});

$app->get('/get-comments', function(Request $request) use($app){
  $app['monolog']->addDebug('logging output.');

  // if($app['session']->get('user')){
  //   return $app->redirect('/');
  // }
    $comments = $app['db']->select('comment', '*');
  

  if(count($comments) < 1){
    return new Response('No comments', 404);
  }
  $reporter = '/img/reporter.jpg';
  $size = 60;
  $count = 1;

  foreach ($comments as &$value) {
    $user = $app['db']->select('user', ['fname', 'lname', 'email'], ['uid' => $value['user_id']]);
    if($user != null)
      $value['gravatar_url'] = "https://www.gravatar.com/avatar/" . md5( strtolower( trim( $user[0]['email'] ) ) ) . "?d=" . urlencode( $default ) . "&s=" . $size;
      $value['username'] = $user['fname'].' '.$user['lname'];
      $value['count'] += $count++;
  }

  return new Response(json_encode($comments), 200);
});


$app->post('/register', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');

  if (ctype_alpha($request->get('firstname')) === false)
  {
          return new Response('First Name should only contain Alphabets!', 500);
  }

  if (ctype_alpha($request->get('lastname')) === false)
  {
    return new Response('Last Name should only contain Alphabets!', 500);
  }

  $userdata = $app['db']->select('aadhartbl', ['firstname', 'lastname', 'aadharno'],[
    'firstname' => $request->get('firstname'),
    'lastname' => $request->get('lastname'),
    'aadharno' => md5($request->get('aadhar'))
    ]);

  if(count($userdata) < 1){
    return new Response('Aadhar does not exists or invalid!', 500);
  }

  $userdata = $app['db']->select('user', ['fname', 'lname', 'aadhar'],[
    'fname' => $request->get('firstname'),
    'lname' => $request->get('lastname'),
    'aadhar' => md5($request->get('aadhar'))
    ]);

  if(count($userdata) > 0){
    return new Response('User already exists!', 500);
  }

  $str = 'abcdefgijklmnopqrstuvwxyz';
  $shuffled = str_shuffle($str);
  $hash = md5($shuffled);

  $app['db']->insert('user', [
    'fname' => $request->get('firstname'),
    'lname' => $request->get('lastname'),
    'uname' => $request->get('email'),
    'email' => $request->get('email'),
    'aadhar' => md5($request->get('aadhar')),
    'state' => $request->get('state'),
    'password' => md5($request->get('pass')),
    'active' => 0,
    'hash' => $hash
  ]);

  try {
    //Server settings
    $app['mail']->SMTPDebug = 2;                                 // Enable verbose debug output
    $app['mail']->isSMTP();                                      // Set mailer to use SMTP
    $app['mail']->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
    $app['mail']->SMTPAuth = true;                               // Enable SMTP authentication
    $app['mail']->Username = 'thewirecoy@gmail.com';                 // SMTP username
    $app['mail']->Password = 'Success@2020';                           // SMTP password
    $app['mail']->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    $app['mail']->Port = 587;                                    // TCP port to connect to

    //Recipients
    $app['mail']->setFrom('thewirecoy@gmail.com', 'Petition waale');
    $app['mail']->addAddress($request->get('email'), $request->get('firstname') . " " . $request->get('lastname'));     // Add a recipient

    //Content
    $app['mail']->isHTML(true);                                  // Set email format to HTML
    $app['mail']->Subject = 'Pending Action';
    $app['mail']->Body    = 'To complete registration open this verfication link or paste in browser url -> <a href="https://polar-oasis-15100.herokuapp.com/verify/'.md5($request->get('aadhar')).'/'. $hash .'">https://polar-oasis-15100.herokuapp.com/verify/'.md5($request->get('aadhar')).'/'. $hash .'</a>';
    $app['mail']->AltBody = 'To complete registration open this verfication link or paste in browser url -> https://polar-oasis-15100.herokuapp.com/verify/'.md5($request->get('aadhar')).'/'. $hash;

    $app['mail']->send();
    echo 'Message has been sent';
  } catch (Exception $e) {
      echo 'Message could not be sent.';
      echo 'Mailer Error: ' . $app['mail']->ErrorInfo;
      return new Response('Error sending mail', 504);
  }

  return new Response('Done', 200);
});

$app->get('/verify/{aadhar}/{hash}', function($aadhar, $hash) use($app) {
  $app['monolog']->addDebug('logging output.');

  $data = $app['db']->select('user', ['fname'], [
    'aadhar' => $aadhar,
    'hash'  => $hash,
    'active' => 0
  ]);

  if(count($data) < 1){
    return $app['twig']->render('verify.twig', ['title'=> 'Mail Verification', 'error' => 'The verification link is either expired or invalid!']);
  }

  $app['db']->update('user', ['active' => 1], ['aadhar' => $aadhar]);

  return $app['twig']->render('verify.twig', ['title'=> 'Mail Verification', 'success' => 'Hey ' . $data[0]['fname']. ', your account has been verified! Proceed to login.' ]);

});


$app->get('/login', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');

  $redirect_url = $request->query->get('redirect') != null ? $request->query->get('redirect') : '/';
  if($app['session']->get('user')){
    return $app->redirect($redirect_url);
  }

  return $app['twig']->render('login.twig', ['title' => 'Log In', 'redirect' => $redirect_url]);
});

$app->get('/create-petition', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  if(!$app['session']->get('user')){
    return $app->redirect('/login?redirect=/create-petition');
  }

  return $app['twig']->render('create-petition.twig', ['title' => 'Create Petition']);
});

$app->post('/checklogin', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');

  $data = $app['db']->select('user', '*', [
    'uname' => $request->get('mail'),
    'password' => md5($request->get('pass')),
    'active' => 1
  ]);

  if(count($data) < 1){
    return new Response('Invalid credentials!', 500);
  }

  $app['session']->set('user', array(
    'uid' =>  $data[0]['uid'],
    'username' => $request->get('mail'),
    'aadhar' => $data[0]['aadhar'],
    'fname' => $data[0]['fname'],
    'lname' => $data[0]['lname'],
    'state' => $data[0]['state']
    ));

    $app['twig']->addGlobal('user', $app['session']->get('user'));

    return new Response('Authenticated Successfully!');

});

$app->get('/logout', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  $app['user'] = null;
  $app['session']->remove('user');
  return $app->redirect('/');
});


//show single petition
$app->get('/single-petition/{id}', function($id) use($app){
  $app['monolog']->addDebug('logging output.');

  $prepare = [];
  

  $data = $app['db']->select('petition', '*', ['id' => $id]);
  if(count($data) < 1){
    return $app->redirect('/');   //TODO : Redirect to 404
  }

  $petition = $data[0];

  $petition['signed'] = FALSE;

  if($app['session']->get('user')){
    $prepare['user'] = $app['user'];
    $signs = $app['db']->select('comment', 'id', [
      'user_id' =>  $app['user']['uid'],
      'petition_id' => $id
      ]);
      if(count($signs) > 0){
        $petition['signed'] = TRUE;
      }
  }
  else{
    $user = null;
  }

  $petition['sign_percentage'] = $petition['currentsign'] / $petition['targetsign'];


  $prepare['title'] = 'Petition : ' . $petition['title'];
  $prepare['petition'] = $petition;

  return $app['twig']->render('single-petition.twig', $prepare);
});

//show all petitions
$app->get('/petition-listing', function() use($app){
  $app['monolog']->addDebug('logging output.');

  // if($app['session']->get('user')){
  //   return $app->redirect('/');
  // }

  $petitions = $app['db']->select('petition', '*');
  if(count($petitions) < 1){
    return $app->redirect('/');   //TODO : Redirect to 404
  }

  return $app['twig']->render('petition-listing.twig', ['title'=> 'All Petitions']);
});

$app->get('/get-petitions/{count}', function(Request $request) use($app){
  $app['monolog']->addDebug('logging output.');

  // if($app['session']->get('user')){
  //   return $app->redirect('/');
  // }
    $count = $request->get('count');
  if($count != 0){
    $petitions = $app['db']->select('petition', '*', ['LIMIT' => $count]);
  }
  else{
    $petitions = $app['db']->select('petition', '*');
  }

  if(count($petitions) < 1){
    return new Response('No petitions', 404);
  }

  return new Response(json_encode($petitions), 200);
});

$app->get('/get-comments/{petition_id}/{count}', function(Request $request) use($app){
  $app['monolog']->addDebug('logging output.');

  // if($app['session']->get('user')){
  //   return $app->redirect('/');
  // }
    $count = $request->get('count');
  if($count != 0){
    $comments = $app['db']->select('comment', '*', ['petition_id' => $request->get('petition_id')], ['LIMIT' => $count]);
  }
  else{
    $comments = $app['db']->select('comment', '*', ['petition_id' => $request->get('petition_id')]);
  }

  if(count($comments) < 1){
    return new Response('No comments', 404);
  }
  $reporter = 'img/reporter.jpg';
  $size = 60;
  $count = 1;

  foreach ($comments as &$value) {
    $user = $app['db']->select('user', ['fname', 'lname', 'email'], ['uid' => $value['user_id']]);
    if(count($user) > 0){
      $value['gravatar_url'] = "https://www.gravatar.com/avatar/" . md5( strtolower( trim( $user[0]['email'] ) ) ) . "?d=" . urlencode( "http://polar-oasis-15100.herokuapp.com/" . $reporter ) . "&s=" . $size;
      $value['username'] = $user[0]['fname'].' '.$user[0]['lname'];
      $value['count'] = $count++;
    }
  }

  return new Response(json_encode($comments), 200);
});

$app->post('/post-petition', function(Request $request) use($app){

  try{
  $app['db']->insert('petition', [
    'title' => $request->get('petition-title'),
    'description' => $request->get('petition-description'),
    'targetsign' => $request->get('target'),
    'bannerimage' => $request->get('petition-banner'),
    'imagedescription' => $request->get('photo-description'),
    'photos' => $request->get('petition-photo'),
    'letter' => $request->get('petition-letter'),
    'recepient' => $request->get('petition-recipient-name'),
    'recepientdesig' => $request->get('petition-recipient-designation'),
    'createdby' => $app['user']['fname'].' '.$app['user']['lname'],
    'createdon' => date("F j, Y, g:i a")
      ]);
  }
  catch(Exception $er){
     return new Response("Error occurred", 500);
  }

  return new Response("Petition created", 200);
});

//profile 
$app->get('/profile', function() use($app) {
  $app['monolog']->addDebug('logging output.');

//if($app['session']->get('user')){
  // return $app->redirect('/profile');
// }

 //$user = $app['db']->select('user', '*', ['id' => $id]);

  return $app['twig']->render('profile.twig', ['title' => 'Profile']);
});

//contact
$app->get('/contact', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('contact.twig', ['title' => 'Contact Us']);
});

$app->post('/sign-petition', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');
  if(!$app['session']->get('user')){
    return new Response('User not logged in', 403);
  }

  $comment = $request->get('comment');
  $data = analyze_sentiment($comment);
  $sentiment = $data['sentiment'];

  $app['db']->insert('comment', [
    'petition_id' => $request->get('petition_id'),
    'user_id'     => $app['user']['uid'],
    'comment'     => $request->get('comment'),
    'date'        => date("F j, Y, g:i a"),
    'score'       => $sentiment['score'],
    'magnitude'       => $sentiment['magnitude']
  ]);

  $signs = $app['db']->count('comment', [
    'petition_id' => $request->get('petition_id')
  ]);

  $app['db']->update('petition', [
    'currentsign' => $signs
  ],[
    'id' => $request->get('petition_id')
  ]);

  return new Response('Petition signed', 200);

});

$app->post('/analyze', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');
  $comment = $request->get('comment');
  $data = analyze_sentiment($comment);
  $sentiment = $data['sentiment'];
  $annotation = $data['annotation']->sentences();

  return new Response(json_encode($sentiment), 200);
});

$app->get('/all-comments/{petition_id}', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');
  $petition_id = $request->get('petition_id');
  $prepare = [];

  if($app['session']->get('user')){
    $prepare['user'] = $app['user'];
  }
  else{
    $user = null;
  }

  $data = $app['db']->select('petition', '*', ['id' => $petition_id]);
  if(count($data) < 1){
    return $app->redirect('/');   //TODO : Redirect to 404
  }
  

  $petition = $data[0];
  $petition['sign_percentage'] = $petition['currentsign'] / $petition['targetsign'];


  $prepare['title'] = 'All Comments : ' . $petition['title'];
  $prepare['petition'] = $petition;

  return $app['twig']->render('comments.twig', $prepare);
  
});

$app->post('/analyze-all', function(Request $request) use($app) {
  $app['monolog']->addDebug('logging output.');
  
  $comments = $app['db']->select('comment', ['id','comment']);

  foreach ($comments as $comment) {
    $data = analyze_sentiment($comment['comment']);
    $app['db']->update('comment', [
      'score' => $data['sentiment']['score'],
      'magnitude' => $data['sentiment']['magnitude']
    ],
    [
      'id' => $comment['id']
    ]);
  }
  

  return new Response("Done! All OK!", 200);
});


$app->run();
