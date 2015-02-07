# WebFinger CLI script

This is a simple CLI script similar to [Unix' finger protocol](https://en.wikipedia.org/wiki/Finger_protocol) to get User informations via WebFinger.

Example:

`$ webfinger acct:pfefferle@notizblog.org`

## Install

Install using Composer

`$ composer global require 'pfefferle/webfinger-cli'`

## Build phar file

To build the phar file, install [Box](http://box-project.org)

`$ composer global require 'kherge/box=~2.4' --prefer-source`

and run

`$ box build -v`
