# WebFinger CLI

A small command line tool, similar to [Unix' finger](https://en.wikipedia.org/wiki/Finger_protocol), that looks up people and resources via [WebFinger](https://webfinger.net/) ([RFC 7033](https://www.rfc-editor.org/rfc/rfc7033)). Works with Mastodon and everything else in the Fediverse.

```
$ webfinger pfefferle@mastodon.social
```

The resource can be given as `user@host`, `@user@host`, `acct:user@host` or any URL.

## Options

| Option | Description |
| --- | --- |
| `--json`, `-j` | Print the raw JRD document instead of the formatted view |
| `--insecure`, `-i` | Fall back to plain HTTP if HTTPS fails |
| `--no-profile` | Skip fetching the h-card from the profile page |

## Install

Via Composer:

```
$ composer global require pfefferle/webfinger-cli
```

Or download `webfinger.phar` from the [latest release](https://github.com/pfefferle/webfinger-cli/releases/latest), make it executable and put it on your `PATH`.

Requires PHP 8.2 or newer with the curl extension.

## Development

```
$ composer install
$ composer test
```

### Building the phar

The phar is built with [Box](https://github.com/box-project/box). Install it with `brew install box` or `phive install humbug/box`, then run

```
$ composer install --no-dev
$ composer build
```

which writes `build/webfinger.phar`. Pushing a tag builds the phar on GitHub Actions and attaches it to the release automatically.
