jQuery(function($) {
  // Set variables.
  var GOCHISO_SERVER = 'https://sandbox.palni.org/andrew/gochiso/';
  var WORLDCAT_INSTITUTION_ID = '945'; // Again, using Goshen's identifier, because that's what I have access to.

  // Check Discovery and oaDOI for full text.
  var processDigitalAvailabilityResults = function(data) {
    if (data.match != null) {
      var digitalAvailabilityAlert = $('#digital-availability .alert');

      digitalAvailabilityAlert.find('.title').html(data.match.title);

      if (data.match.abstract) {
        digitalAvailabilityAlert.find('.abstract').html(data.match.description.split(/(\n|<br\/?>)/)[0]);
      } else {
        digitalAvailabilityAlert.find('.abstract').remove();
      }

      if (data.match.authors) {
        digitalAvailabilityAlert.find('.authors').text(data.match.authors);
      } else {
        digitalAvailabilityAlert.find('.authors').text(data.match.aulast);
      }

      var volumeInformation = [];

      if (data.match.container != null) {
        digitalAvailabilityAlert.find('.title').addClass('item-type-article');

        digitalAvailabilityAlert.find('.publication').append($('<i>').html(data.match.container));

        if (data.match.volume) volumeInformation.push('v' + data.match.volume);
        if (data.match.issue) volumeInformation.push('n' + data.match.issue);
        if (data.match.date) volumeInformation.push('(' + data.match.date + ')');

        digitalAvailabilityAlert.find('.publication').append(', ' + volumeInformation.join(' '));
      } else {
        digitalAvailabilityAlert.find('.title').addClass('item-type-monograph');
      }

      if (data.match.fulltext != null) {
        _.forEach(data.match.fulltext, function(fullTextLink) {
          digitalAvailabilityAlert.find('.full-text-links').append(makeFullTextLink(fullTextLink));
        });
      }

      if (WORLDCAT_INSTITUTION_ID) {
        digitalAvailabilityAlert.find('#discovery-link').attr('href', 'https://' + WORLDCAT_INSTITUTION_ID + '.on.worldcat.org/search?sortKey=RELEVANCE&databaseList=638&queryString=ti%3A' + encodeURIComponent(data.match.title));
      }

      // Display.
      $('#digital-availability').hide().removeClass('hidden').fadeIn();
    }
  };

  var makeFullTextLink = function(data) {
    var listItem = $('<li></li>');
    var link = $('<a></a>');

    listItem.append(link);

    link.attr('href', data.link);
    link.attr('target', '_blank');
    link.addClass('alert-link');
    link.text(data.label);

    return listItem;
  };

  // Set the base URL of gochiso.
  var baseURL = GOCHISO_SERVER;

  // Somehow generate an OpenURL query string and put it here.
  var queryString = window.location.search.substr(1);

  if (queryString) {
    $.when(
      $.getJSON(baseURL + 'discovery.php?' + queryString),
      $.getJSON(baseURL + 'oadoi.php?' + queryString),
      $.getJSON(baseURL + 'openaccessbutton.php?' + queryString)
    )
    .done(function(discoveryData, oadoiData, oabuttonData) {
      // If one of these fails, this doesn't run... so then what? //!

      discoveryData = discoveryData[0];
      oadoiData = oadoiData[0];
      oabuttonData = oabuttonData[0];

      // This would be the appropriate time to handle errors.

      var results = [];

      // Appending these in this order prefers data from oaDOI. I think I prefer the oadoiData because most of it comes from CrossRef.
      // Note: the order in which these are appended also affects the order of full-text link display, which is why OpenAccessButton isn't first.
      if (discoveryData.match != null) {
        results.push(discoveryData.match);
      }

      if (oabuttonData.match != null) {
        results.push(oabuttonData.match);
      }

      if (oadoiData.match != null) {
        results.push(oadoiData.match);
      }

      // Crude merging of the data...
      var fulltext = _(results).flatMap('fulltext').uniqBy('url').value();

      var data = { match: {} };

      _.forEach(results, function(match) {
        _.extend(data.match, match);
      });

      data.match.fulltext = fulltext;
      // ... end crude merging.

      if (fulltext.length) {
        processDigitalAvailabilityResults(data);
      }
    });
  }
});
