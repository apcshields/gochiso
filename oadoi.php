<?php
  require_once 'includes/defaults.inc.php';

  use GuzzleHttp\Client;
  // use GuzzleHttp\Psr7\Request; //d
  // use GuzzleHttp\TransferStats; //d
  // use GuzzleHttp\Psr7; //d
  
  // Set up some exceptions we can throw/catch for flow.
  class BadCrossrefStatus extends Exception {};
  class BadCrossrefMessageType extends Exception {};
    
  class BadOadoiResponse extends Exception {};
  
  // Send a header setting our content type to JSON.
  header('Content-Type: application/json'); //!

  $response = [];
  
  try {
    include_once 'includes/contextobject.inc.php';
    
    $ctx_obj = new ContextObject();
    
    // Query CrossRef.
    $crossrefClient = new Client([
      'base_uri' => 'http://api.crossref.org/',
      'timeout' => 5.0
    ]);
    
    $query = [];
    
    $query['query.title'] = ($_1 = $ctx_obj->getBookTitle()) ? $_1 : ((!empty($ctx_obj->atitle)) ? $ctx_obj->atitle : null);
    $query['query.author'] = $ctx_obj->aulast;
    
    $crossrefResponse = $crossrefClient->get('/works', [
      'query' => $query,
      // 'on_stats' => function (TransferStats $stats) use (&$url) { //d
      //     $url = $stats->getEffectiveUri();
      // }
    ]);
    
    // echo('<pre>' . print_r($url, true) . '</pre>'); //d
    
    $crossrefData = json_decode(utf8_encode($crossrefResponse->getBody()), true);

    // echo('<pre>' . print_r($crossrefData, true) . '</pre>'); //d
    
    // Make sure we're getting what we expect.
    if (!$crossrefData['status'] == 'ok') throw new BadCrossrefStatus();
    if (!$crossrefData['message-type'] == 'work-list') throw new BadCrossrefMessageType("Message type is not 'work-list'.");
    
    try {
      $resultItem = $crossrefData['message']['items'][0];
      $resultDOI = $resultItem['DOI'];
      
      // echo('<pre>' . print_r($resultItem, true) . '</pre>'); //d
      // echo($resultDOI); //d
    } catch (Exception $e) {
      throw new BadCrossrefMessageType('Expected message contents missing.');
    }
    
    // Query oaDOI.
    $oadoiClient = new Client([
      'base_uri' => 'https://api.oadoi.org/',
      'timeout' => 5.0
    ]);
    
    $oadoiResponse = $oadoiClient->get($resultDOI);
    
    $oadoiData = json_decode(utf8_encode($oadoiResponse->getBody()), true);
    
    if (!count($oadoiData['results'])) throw new BadOadoiResponse('No results.');
    
    $match = [];
    
    for ($counter = 0, $length = count($oadoiData['results']); $counter < $length; $counter++) {
      if (isset($oadoiData['results'][$counter]['free_fulltext_url'])) {
        if (isset($oadoiData['results'][$counter]['_title'])) $match['title'] = $oadoiData['results'][$counter]['_title'];
        if (count($resultItem['container-title'])) $match['container'] = $resultItem['container-title'][0];
        if (isset($resultItem['issued']) && isset($resultItem['issued']['date-parts']) && count($resultItem['issued']['date-parts']) && count($resultItem['issued']['date-parts'][0])) $match['date'] = $resultItem['issued']['date-parts'][0][0];
        if (isset($resultItem['volume'])) $match['volume'] = $resultItem['volume'];
        if (isset($resultItem['issue'])) $match['issue'] = $resultItem['issue'];
        if (isset($resultItem['author']) && count($resultItem['author']) && isset($resultItem['author'][0]['family'])) $match['aulast'] = $resultItem['author'][0]['family'];
        
        $match['authors'] = (isset($resultItem['author'])) ? humanizeCrossrefAuthors($resultItem['author']) : '';
        
        $match['abstract'] = '';
        
        $match['fulltext'] = [];
      
        $match['fulltext'][] = [
          'link' => $oadoiData['results'][$counter]['free_fulltext_url'],
          'label' => 'Full text'
        ];
        
        break;
      }
    }
    
    $response['match'] = $match;
    
    // echo('<pre>' . print_r($oadoiData, true) . '</pre>'); //d
    // echo('<pre>' . print_r($response, true) . '</pre>'); //d
  } catch (BadCrossrefStatus $e) {
    // echo($e->getMessage()); //!
  } catch (BadCrossrefMessageType $e) {
    // echo($e->getMessage()); //!
  } catch (GuzzleHttp\Exception\ConnectException $e) {
    // echo('<h4>Error</h4>'); //!
    // echo('<pre>' . Psr7\str($e->getRequest()) . '</pre>'); //!
    
    // if ($e->hasResponse()) { //!
    //     echo('<pre>' . Psr7\str($e->getResponse()) . '</pre>');
    // }
  } catch (Exception $e) {
    // throw $e; //!
  } finally {
    echo(json_encode($response, JSON_PRETTY_PRINT)); //! JSON_PRETTY_PRINT is for development only.
  }
  
  function humanizeCrossrefAuthors($author) {
    $authorsString = '';
    
    for ($counter = 0, $length = count($author); $counter < $length; $counter++) {
      if (isset($author[$counter]['family'])) {
        $authorString = $author[$counter]['family'];
        
        if (isset($author[$counter]['given'])) {
          $authorString .= ', ' . $author[$counter]['given'];
        }
        
        if ($authorsString) {
          $authorsString .= '; ';
        }
        
        $authorsString .= $authorString;
      }
    }
    
    return $authorsString;
  }
  
  // http://api.crossref.org/works?query.title=Posttraumatic+stress+disorder+and+suicide+risk+among+veterans%3A+a+literature+review&query.author=Pompili
