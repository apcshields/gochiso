<?php 
  require_once 'includes/defaults.inc.php';
  
  // Set up configuration.
  // Google Scholar
  $googleScholarSearchUrl = "https://scholar.google.com/scholar";
  
  $searchQueryParameters = [];
  $searchQueryParameters['oi'] = 'gsb90';
  $searchQueryParameters['output'] = 'gsb';
  $searchQueryParameters['hl'] = 'en';
  
  // Get Guzzle ready.
  use GuzzleHttp\Client;

  $guzzleClient = new Client([
    'timeout'  => 2.0,
  ]);
  
  // Send a header setting our content type to JSON.
  header('Content-Type: application/json');
  
  $response = [];
  $response['match'] = null;
  
  try {
    include_once 'includes/contextobject.inc.php';
    
    $ctx_obj = new ContextObject();
    
    // echo('<pre>' . print_r($ctx_obj, true) . '</pre>'); //d
    
    // Currently, we’re just trying to match on the title.
    $rft = [];
    $rft['title'] = ($_1 = $ctx_obj->atitle) ? $_1 : $ctx_obj->getBookTitle();
    
    $searchQueryParameters['q'] = '"' . preg_replace('/\p{P}/', ' ', $rft['title']) . '"';
    
    // echo('<pre>' . print_r($searchQueryParameters, true) . '</pre>'); //d
    
    $googleScholarResponse = $guzzleClient->get($googleScholarSearchUrl, [ 'query' => $searchQueryParameters ]);
    
    $response['search'] = $googleScholarSearchUrl . '?'. http_build_query($searchQueryParameters); // With a little rewriting, this can probably be gotten from Guzzle.
    
    // $response['headers'] = $googleScholarResponse->getHeaders(); //d
    // $response['raw'] = utf8_encode($googleScholarResponse->getBody()); //d
    
    $data = json_decode(utf8_encode($googleScholarResponse->getBody()), true);
    
    if (count($data['r']) > 0) {
      // Be pretty naive about a match—just pick the first one and ask the user.
      $match = $data['r'][0];
      
      $response['match'] = [];
      $response['match']['title'] = html_entity_decode(strip_tags($match['t']), ENT_QUOTES);
      $response['match']['bibliographic'] = html_entity_decode(strip_tags($match['m']), ENT_QUOTES);
      $response['match']['abstract'] = preg_replace('/^Abstract /i', '', html_entity_decode(strip_tags($match['s']), ENT_QUOTES));
      $response['match']['googleScholarLink'] = $googleScholarSearchUrl . '?' . http_build_query(['q' => $searchQueryParameters['q']]);
      $response['match']['fulltext'] = [];
      
      if (isset($match['l'])) {
        if (isset($match['l']['l']) && isset($match['l']['l']['u'])) {
          // Our potential match has a link to potential full-text in our link resolver.
          $response['match']['fulltext']['local'] = [
            'label' => html_entity_decode(strip_tags($match['l']['l']['l']), ENT_QUOTES),
            'link' => $match['l']['l']['u']
          ];
        }
        
        if (isset($match['l']['g']) && isset($match['l']['g']['u'])) {
          // Our potential match has a link to potential full-text on the open web.
          $response['match']['fulltext']['web'] = [
            'label' => html_entity_decode(strip_tags($match['l']['g']['l']), ENT_QUOTES),
            'link' => $match['l']['g']['u']
          ];
        }
      }
      
      // echo('<pre>' . print_r(json_encode($response, JSON_PRETTY_PRINT), true) . '</pre>'); //d
      
      if (!count($response['match']['fulltext'])) {
        // We don’t have at least one potential full-text link, so send an empty response.
        $response['match'] = null;
      }
    }
  } catch (Exception $e) {
    $response['error'] = utf8_encode($e->getMessage());
  } finally {
    // Send our response.
    echo(json_encode($response));
  }
