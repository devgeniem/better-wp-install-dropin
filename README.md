![geniem-github-banner](https://cloud.githubusercontent.com/assets/5691777/14319886/9ae46166-fc1b-11e5-9630-d60aa3dc4f9e.png)
# WP Dropin: Better install.php
[![Latest Stable Version](https://poser.pugx.org/devgeniem/better-wp-install-dropin/v/stable)](https://packagist.org/packages/devgeniem/better-wp-install-dropin) [![Total Downloads](https://poser.pugx.org/devgeniem/better-wp-install-dropin/downloads)](https://packagist.org/packages/devgeniem/better-wp-install-dropin) [![Latest Unstable Version](https://poser.pugx.org/devgeniem/better-wp-install-dropin/v/unstable)](https://packagist.org/packages/devgeniem/better-wp-install-dropin) [![License](https://poser.pugx.org/devgeniem/better-wp-install-dropin/license)](https://packagist.org/packages/devgeniem/better-wp-install-dropin)

This `install.php` dropin doesn't install bloat from default install.php and sets a few opionated wp options.

It doesn't send you email after installing new WordPress either.

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
