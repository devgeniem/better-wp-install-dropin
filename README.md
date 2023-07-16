<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/figuren-theater/install.php">
    <img src="https://raw.githubusercontent.com/figuren-theater/logos/main/favicon.png" alt="figuren.theater Logo" width="100" height="100">
  </a>

  <h1 align="center">figuren.theater | install.php</h1>

  <p align="center">
    WordPress installer-dropin for the <a href="https://figuren.theater">figuren.theater</a> Multisite network.
    <br /><br /><br />
    <a href="https://meta.figuren.theater/blog"><strong>Read our blog</strong></a>
    <br />
    <br />
    <a href="https://figuren.theater">See the network in action</a>
    •
    <a href="https://mein.figuren.theater">Join the network</a>
    •
    <a href="https://websites.fuer.figuren.theater">Create your own network</a>
  </p>
</div>

## About 

Forked from [devgeniem/better-wp-install-dropin: Slightly modified WordPress installer.php dropin for cleaner content after installation.](https://github.com/devgeniem/better-wp-install-dropin)

---

This `install.php` dropin doesn't install bloat from default install.php and sets a few opionated wp options.

It doesn't send you email after installing new WordPress either.

## Current options
- ~~Set empty page as Front page~~
- Don't use any widgets
- Use empty blog description ( if someone forgots to change that )
- Use `/%category%/%postname%/` permalink
- ~~Don't install any articles~~
- Use the `ft_install_defaults` hook to add custom functionality

## Installation
You can copy `install.php` to your `wp-content` folder. Just plug&play.

OR you can use composer so that you can automatically update it too. Put these in your composer.json:
```json
{
    "require": {
        "figuren-theater/install.php": "^1.0"
    },
    "extra": {
        "dropin-paths": {
            "htdocs/wp-content/": ["type:wordpress-dropin"],
        }
    }
}
```

## License

This project is licensed under the **GPL-3.0-or-later**, see the [LICENSE](/LICENSE) file for
details

## Acknowledgments to the original maintainers

- [@onnimonni](https://github.com/onnimonni)
- [@Nomafin](https://github.com/Nomafin)
