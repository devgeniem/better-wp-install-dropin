![geniem-github-banner](https://cloud.githubusercontent.com/assets/5691777/14319886/9ae46166-fc1b-11e5-9630-d60aa3dc4f9e.png)
# WP Dropin: Better install.php
[![Build Status](https://travis-ci.org/devgeniem/better-wp-install-dropin.svg?branch=master)](https://travis-ci.org/devgeniem/better-wp-install-dropin) [![Latest Stable Version](https://poser.pugx.org/devgeniem/better-wp-install-dropin/v/stable)](https://packagist.org/packages/devgeniem/better-wp-install-dropin) [![Total Downloads](https://poser.pugx.org/devgeniem/better-wp-install-dropin/downloads)](https://packagist.org/packages/devgeniem/better-wp-install-dropin) [![Latest Unstable Version](https://poser.pugx.org/devgeniem/better-wp-install-dropin/v/unstable)](https://packagist.org/packages/devgeniem/better-wp-install-dropin) [![License](https://poser.pugx.org/devgeniem/better-wp-install-dropin/license)](https://packagist.org/packages/devgeniem/better-wp-install-dropin)

This `install.php` dropin doesn't install bloat from default install.php and sets a few opionated wp options.

It doesn't send you email after installing new WordPress either.

## Current options
- Set empty page as Front page
- Don't use any widgets
- Use empty blog description ( if someone forgots to change that )
- Use `/%category%/%postname%/` permalink
- Don't install any articles

## Installation
You can copy `install.php` to your `wp-content` folder. Just plug&play.

OR you can use composer so that you can automatically update it too. Put these in your composer.json:
```json
{
    "require": {
        "devgeniem/better-wp-install-dropin": "^1.0"
    },
    "extra": {
        "dropin-paths": {
            "htdocs/wp-content/": ["type:wordpress-dropin"],
        }
    }
}
```

## License
GPLv3

## Maintainers
[@onnimonni](https://github.com/onnimonni)

[@Nomafin](https://github.com/Nomafin)
