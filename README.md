# RSS Feed Attachment Downloader

This will read an RSS feed in and download attachments in the "guid" field of the feed items. 
I made it so I could automate downloading [Never Not Funny](http://www.nevernotfunny.com) videos because Plex can't handle 
password protected podcast feeds.

# Usage
- Download the file.
- Edit the file and set the `$url` and `$savePath` variables.
- Run the file like `php -f feed-downloader.php`.
