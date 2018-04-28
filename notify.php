<?php
  /*
    This file is intended to email the technical services librarian whenever a 
    link proposed by the link resolver does not work.
  */
  
  require_once 'includes/defaults.inc.php';
  
  if (php_sapi_name() != 'cli') { // Don't continue unless this has been run from the command line.
    exit();
  }
  
  $options = getopt('', [
    'exception:'
  ]);
  
  if ($options) {
    require_once "includes/Mailer.class.php";
    
    $mailer = new Mailer;
    $mailer->setFromName('Goshen College Library');
    $mailer->setFromAddress('librarytechservices@goshen.edu');
    $mailer->setToAddress('librarytechservices@goshen.edu');
    $mailer->setSubjectLine('[digital-availability] Link resolver error');
    $mailer->setHTML(TRUE);
    
    ob_start();
    
    $exception_string = json_encode(json_decode($options['exception']), JSON_PRETTY_PRINT);
    
?>
<!doctype html>
<html class="no-js" lang="">
  <head>
    <meta charset="utf-8">
    <title>[digital-availability] Link resolver error</title>
  </head>
  <body>
    <p><b>Link resolver error:</b></p>
    <pre><?=$exception_string; ?></pre>
  </body>
</html>
<?php
    
    $message = ob_get_clean();
    
    $mailer->setContent($message);
    
    $mailer->sendEmail();
  }
