<?php
  require_once 'includes/defaults.inc.php';

  use GuzzleHttp\Client;

  // Send a header setting our content type to JSON.
  header('Content-Type: application/json'); //!

  $response = [];

  try {
    // Set up Guzzle configuration.
    $guzzleClient = new Client([
      'timeout' => 2.0
    ]);

    include_once "includes/contextobject.inc.php";

    $ctx_obj = new ContextObject();

    $openUrlParameters = [];

    $openUrlParameters['rft.title']  = ($_1 = $ctx_obj->getBookTitle()) ? $_1 : $ctx_obj->getJournalTitle();
    if (!empty($ctx_obj->issn)) $openUrlParameters['rft.issn'] = $ctx_obj->issn;
    if (!empty($ctx_obj->eissn)) $openUrlParameters['rft.eissn'] = $ctx_obj->eissn;
    if (!empty($ctx_obj->isbn)) $openUrlParameters['rft.isbn'] = $ctx_obj->isbn;

    if (!empty($ctx_obj->date)) $openUrlParameters['rft.date'] = $ctx_obj->date;
    if (!empty($ctx_obj->volume)) $openUrlParameters['rft.volume'] = $ctx_obj->volume;
    if (!empty($ctx_obj->issue)) $openUrlParameters['rft.issue'] = $ctx_obj->issue;
    if (!empty($ctx_obj->spage)) $openUrlParameters['rft.spage'] = $ctx_obj->spage;
    if (!empty($ctx_obj->atitle)) $openUrlParameters['rft.atitle'] = $ctx_obj->atitle;
    if (!empty($ctx_obj->aulast)) $openUrlParameters['rft.aulast'] = $ctx_obj->aulast;

    $openUrlParameters['rft.institution_id'] = INSTITUTION_ID;
    $openUrlParameters['wskey'] = WSKEY;

    // http://www.oclc.org/developer/develop/web-services/worldcat-knowledge-base-api/openurl-resource.en.html
    $kbResponse = $guzzleClient->get('http://worldcat.org/webservices/kb/openurl/resolve', [
      'query' => $openUrlParameters
    ]);

    $data = json_decode(utf8_encode($kbResponse->getBody()), true);

    // echo('<pre>' . print_r($data, true) . '</pre>'); //d

    $match = [];

    if (count($data)) {
      for ($index = 0, $length = count($data); $index < $length; $index++) {
        $record = $data[$index];

        if ($link = (isLinkGood($record, 'linkerurl') ?: (isLinkGood($record, 'url') ?: false))) {
          $kb_ctx_obj = new ContextObject($record['linkerurl']); // Falls back to page query string if this is empty.

          if ($match === []) {
            $match['title'] = ($_1 = $kb_ctx_obj->atitle) ? $_1 : $kb_ctx_obj->getBookTitle();
            if ($kb_ctx_obj->getJournalTitle()) $match['container'] = $kb_ctx_obj->getJournalTitle();
            if (!empty($kb_ctx_obj->date)) $match['date'] = $kb_ctx_obj->date;
            if (!empty($kb_ctx_obj->volume)) $match['volume'] = $kb_ctx_obj->volume;
            if (!empty($kb_ctx_obj->issue)) $match['issue'] = $kb_ctx_obj->issue;
            if (!empty($kb_ctx_obj->aulast)) $match['aulast'] = $kb_ctx_obj->aulast;

            $match['authors'] = trim($ctx_obj->getAuthors()); // Bit of a hack, but OCLC doesn't send much in the way of author information. // I don't like this.

            $match['abstract'] = ''; //!

            $match['fulltext'] = [];
          }

          $match['fulltext'][] = [
            'link' => $link,
            'label' => (isset($record['collection_name'])) ? 'Full text from ' . $record['collection_name'] : 'Full text'
          ];

          $response['match'] = $match;
        }
      }
    }
  } finally {
    echo(json_encode($response, JSON_PRETTY_PRINT)); //! JSON_PRETTY_PRINT is for development only.
  }

  function isLinkGood($array, $key) {
    if (!isset($array[$key])) {
      return false;
    }

    $guzzleClient = new Client([
      'timeout' => 5.0
    ]);

    try {
      $response = $guzzleClient->head($array[$key], [ // We don't need no body.
        'allow_redirects' => true, // Guzzle helpfully follows redirects by default, but we'll make it explicit.
      ]);

      return $array[$key];
    } catch (\GuzzleHttp\Exception\RequestException $e) { // This gets `ClientException`s (4xx), `ServerException`s (5xx), and `TooManyRedirectsException`s, as well as assorted others.
      // This is where we'd check the type of exception and, if appropriate, fire something off to notify a librarian.
      $exception = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'context' => $_SERVER['QUERY_STRING'],
        'link' => $array[$key],
        'key' => $key
      ];

      if ($e->hasResponse()) {
        $exception['status_code'] = $e->getResponse()->getStatusCode();
        $exception['reason_phrase'] = $e->getResponse()->getReasonPhrase();
      }

      if (isset($array['id'])) {
        $exception['title_id'] = $array['id'];
      }

      $exception_string = escapeshellarg(json_encode($exception));

      exec('nohup php ./notify.php --exception=' . $exception_string . ' > /dev/null 2>&1 &');

      return false;
    }
  }
