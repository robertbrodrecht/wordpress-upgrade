# Wordpress Upgrade
Upgrades all WordPress installs in a folder via the command line.

## Config
Here's a sample config. Create a file called `config.json` with something like this in it:

```
{
	"executables": {
		"mysqldump": "path/to/mysqldump",
		"mysql": "path/to/mysql",
		"tar": "path/to/tar",
		"find": "path/to/find",
		"curl": "path/to/curl",
		"du": "path/to/du",
		"cp": "path/to/cp"
	},
	"paths": {
		"sites": dirname($script_path) . "/",
		"backups": $script_path . "backups/",
		"wordpress": $script_path . "wordpress/"
	},
	"include": ["/path/site1.com/", "/path/site2.com/"],
	"exclude": ["/path/site3.com/", "/path/site4.com/"]
}

```

You have to use double quotes to make it valid JSON.

## Command Line Parameters
Most of the command line parameters don't work, but for now, from `upgrade.php --help`:

```
--clean          	Delete any WordPress temp files and download a new copy.
--help           	Show this message.
--no-download    	Disable download of new WordPress.
--no-backup      	Do not perform backups of sites.
--no-upgrade     	Do not perform WordPress upgrade.
--package {value}	Specify with the full URL to a WordPress gzip.
--single {value} 	Specify the path to a single WordPress install to upgrade.
--version {value}	Specify with the current version number to override latest.
```