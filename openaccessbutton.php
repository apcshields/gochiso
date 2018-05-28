<?php
  require_once 'includes/defaults.inc.php';

  use GuzzleHttp\Client;
  use GuzzleHttp\TransferStats;

  // Set up some exceptions we can throw/catch for flow.
  class BadOabuttonResponse extends Exception {};

  // Send a header setting our content type to JSON.
  header('Content-Type: application/json'); //!

  $response = [];

  try {
    include_once 'includes/contextobject.inc.php';

    $ctx_obj = new ContextObject();

    // Query OpenAccessButton.
    $oabuttonClient = new Client([
      'base_uri' => 'https://api.openaccessbutton.org/availability',
      'timeout' => 5.0,
      'headers' => [
        'x-apikey' => OABUTTON_KEY
      ]
    ]);

    $oabuttonResponse = $oabuttonClient->get('', [
      'query' => [
        'url' => ($_1 = $ctx_obj->atitle) ? $_1 : ($ctx_obj->getBookTitle() ? $ctx_obj->getBookTitle() : null)
      ]
    ]);

    $oabuttonData = json_decode(utf8_encode($oabuttonResponse->getBody()), true);

    // $response['match'] = $oabuttonData; //d

    if (@!count($oabuttonData['data']['availability'])) throw new BadOabuttonResponse('No results.');

    $match = [
      'fulltext' => []
    ];

    // Check to see if any of the availability results OpenAccessButton has found are of the 'article' type. If not, don't continue.
    foreach($oabuttonData['data']['availability'] as $availability) {
      if (isset($availability['type']) && $availability['type'] == 'article' && isset($availability['url'])) {
        $match['fulltext'][] = [
          'link' => $availability['url'],
          'label' => 'Full text online'
        ];
      }
    } unset($availability);

    if (count($match['fulltext'])) {
      // Grab the minimal data that OpenAccessButton returns about the article, and add all the URLs that it found.
      if (isset($oabuttonData['data']['availability'][0]['url'])) {
        if (isset($oabuttonData['data']['meta']['article']['title'])) $match['title'] = $oabuttonData['data']['meta']['article']['title'];
      }

      $response['match'] = $match;
    } else {
      throw new BadOabuttonResponse('No article-type results.');
    }
  } catch (GuzzleHttp\Exception\ConnectException $e) {
    // echo('<h4>Error</h4>'); //!
    // echo('<pre>' . Psr7\str($e->getRequest()) . '</pre>'); //!

    // if ($e->hasResponse()) { //!
    //     echo('<pre>' . Psr7\str($e->getResponse()) . '</pre>');
    // }
    $response['error'] = $e->getMessage(); //!
  } catch (Exception $e) {
    // throw $e; //!
    $response['error'] = $e->getMessage(); //!
  } finally {
    echo(json_encode($response, JSON_PRETTY_PRINT)); //! JSON_PRETTY_PRINT is for development only.
  }
