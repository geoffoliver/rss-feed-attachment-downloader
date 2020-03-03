<?php
/**
 * RSS feed attachment downloader.
 *
 * This will read an RSS feed in and download attachments in the "guid" field
 * of the feed items. I made it so I could automate downloading Never Not Funny
 * videos because Plex can't handle password protected podcast feeds.
 *
 * @author Geoff Oliver <geoff@plan8studios.com>
 * @version 1.0.0
 */

// the URL of the feed. you shouldn't need to include a username/password anymore
$url = '';

// where the files should be saved
$savePath = "";

// make sure there's a URL for the feed
if (!$url) {
  echo "You must set the \$url variable.\n";
  exit();
}

// make sure there's a URL for the feed
if (!$savePath) {
  echo "You must set the \$savePath variable.\n";
  exit();
}

// the name of the file that keeps track of when the last downloaded item was
// created on, not necessarily when we actually downloaded it
$lastDlFile = ".last-downloaded";

// this is a time that will get it's value from the $lastDlFile
$lastDlTime = 0;

// give a little feedback
$now = date('Y-m-d');
echo "Downloading feed ({$now})...\n";

// download the feed and convert it to json because i'm lazy
$feed = json_decode(
	json_encode(
		simplexml_load_string(
			file_get_contents($url),
			null,
			LIBXML_NOCDATA
		)
	)
);

// this is the directory where we'll put the files for this feed
$filePath = $savePath . DIRECTORY_SEPARATOR . $feed->channel->title;

// make sure the directory exists
if (!file_exists($filePath)) {
    mkdir($filePath);
}

// last downloaded is kept track of per feed
$lastDlFile = $filePath . DIRECTORY_SEPARATOR . $lastDlFile;

if (file_exists($lastDlFile)) {
    // this has been run before, we should know how when the newest file was created
    $lastDlTime = (int)file_get_contents($lastDlFile);
    echo 'Downloading items created since ' . date('Y-m-d', $lastDlTime) . "\n";
} else {
    // file doesn't exist, just create it and move on
    touch($lastDlFile);
}

// the items in the feed will live here
$items = [];

// convert pubDate to a timestamp
foreach ($feed->channel->item as $i => $item) {
    $item->pubDate = strtotime($item->pubDate);
    $items[] = $item;
}

// sort the items from oldest to newest
usort($items, function($a, $b) {
    if ($a->pubDate === $b->pubDate) {
        return 0;
    }

    return ($a->pubDate < $b->pubDate) ? -1 : 1;
});

// make sure we only look at things newer than the last thing downloaded
$items = array_filter($items, function($item) use ($lastDlTime) {
	return ($item->pubDate > $lastDlTime);
});

// loop over the items and maybe download them
foreach ($items as $item) {
	$url = $item->enclosure->{'@attributes'}->url;
	// nice feedback message
	echo "Downloading \"{$item->title}\" from {$url}\n";

	// all of this to get the goddamn extension for the file
	$parts = explode('?', $url);
	$dots = explode('.', $parts[0]);
	$ext = array_pop($dots);
	$filename = trim($item->title) . '.' . trim($ext);

	// this is where the file we'll download will live
	$fullPath = $filePath . DIRECTORY_SEPARATOR . $filename;

	if (file_exists($fullPath)) {
		// the file already exists, so just skip it
		echo "File {$filename} already exists, skipping download.\n";
		file_put_contents($lastDlFile, $item->pubDate);
	} else {
		if (download($url, $fullPath)) {
			// download success! give some feedback and update the last download timestamp
			echo "\nDownload complete!\n";
			file_put_contents($lastDlFile, $item->pubDate);
		} else {
			// download failed. oh well :shrug:
			echo "\nUnable to download file!\n";
		}
	}
}

// tada!
echo "All done!\n\n";

/**
 * Downloads a file from a URL into a local file
 *
 * @param string $url The URL you want to download.
 * @param string $save The path where the file should be downloaded to.
 * @return bool True on success. False on failure.
 */
function download($url, $save) {
    // open a file for writing
    $output = fopen($save, 'w+');

    // fire up curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FILE, $output); // so curl can write directly to the file
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:70.0) Gecko/20100101 Firefox/70.0");
    // uncomment the block below if you want some shitty progress on the download
    // curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    // curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($resource, $dlSize, $dlTotal, $upSize, $upTotal) {
    //     if ($dlSize > 0) {
    //         $progress = round(($dlTotal / $dlSize) * 100);
    //         $complete = str_pad('', $progress, '=');
    //         echo str_pad($complete, 100 - $progress, '-');
    //     } else {
    //         echo str_pad('', 100, '-');
    //     }
    //     echo "\r";
    // });

    // run curl and get some data... hopefully
    curl_exec($ch);

    // we're done with this, so close it
    fclose($output);
    
    // if file just exists but is empty, it's fucked, so we should delete it
    if (filesize($save) === 0) {
        unlink($save);
    }

    // check for errors
    if (curl_errno($ch)) {
        // this sucks... just dump the error and bail out
        $error = curl_error($ch);
        curl_close($ch);
        var_dump($error);
        return false;
    }

    // close up the curl and call it a day!
    curl_close($ch);

    // yay!!
    return true;
}
